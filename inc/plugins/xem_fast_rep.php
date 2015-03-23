<?php
/**
 * Author: Szczepan 'Xemix' Machaj
 * WWW: xemix.eu / xemix.pl
 * Copyright (c) 2015
 * License: Creative Commons BY-NC-SA 4.0
 * License URL: http://creativecommons.org/licenses/by-nc-sa/4.0/
 */

if(!defined("IN_MYBB")) exit();

global $mybb;

if($mybb->settings['enablereputation'])
{
    $plugins -> add_hook('postbit', ['xem_fast_rep', 'in_post']);
    $plugins -> add_hook('xmlhttp',    ['xem_fast_rep', 'xmlhttp']);
}

function xem_fast_rep_info()
{
    global $lang;
    $lang->load('xem_fast_rep');

    return [
        'name'          => 'xem Fast Reputation',
        'description'   =>  $lang->xem_fast_rep_description,
        'website'       => 'http://xemix.eu',
        'author'        => 'Xemix',
        'authorsite'    => 'http://xemix.eu',
        'version'       => '1.3.1',
        'codename'      => 'xem_fast_rep',
        'compatibility' => '18*'
    ];
}

function xem_fast_rep_install()
{
    global $db, $mybb, $lang;

    $lang->load('xem_fast_rep');

    $setting_group_id = $db->insert_query('settinggroups', [
        'name'        => 'xem_fast_rep_settings',
        'title'       => $db->escape_string($lang->xem_fast_rep_settings_title),
        'description' => $db->escape_string($lang->xem_fast_rep_settings_title),
    ]);

    $settings = [
        [
            'name'        => 'xem_fast_rep_active',
            'title'       =>  $lang->xem_fast_rep_plugin_active,
            'optionscode' => 'yesno',
            'value'       => '1'
        ],
        [
            'name'        => 'xem_fast_rep_show_liked_this',
            'title'       =>  $lang->xem_fast_rep_show_liked_this,
            'optionscode' => 'yesno',
            'value'       => '1'
        ],
    ];

    $i = 1;

    foreach($settings as &$row) {
        $row['gid']         = $setting_group_id;
        $row['title']       = $db->escape_string($row['title']);
        $row['description'] = $db->escape_string($row['description']);
        $row['disporder']   = $i++;
    }

    $db->insert_query_multiple('settings', $settings);

    rebuild_settings();

}

function xem_fast_rep_uninstall()
{
    global $db;

    $setting_group_id = $db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='xem_fast_rep_settings'"),
        'gid'
    );

    $db->delete_query('settinggroups', "name='xem_fast_rep_settings'");
    $db->delete_query('settings', 'gid='.$setting_group_id);

    rebuild_settings();
}

function xem_fast_rep_is_installed()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='xem_fast_rep_settings'");
    return (bool)$db->num_rows($query);
}

function xem_fast_rep_activate()
{
    include_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'postbit',
        '#' . preg_quote('{$post[\'attachments\']}') . '#i',
        '{$post[\'xem_fast_rep\']}
    {$post[\'attachments\']}'
    );

    find_replace_templatesets(
        'postbit_classic',
        '#' . preg_quote('{$post[\'attachments\']}') . '#i',
        '{$post[\'xem_fast_rep\']}
                    {$post[\'attachments\']}'
    );

    find_replace_templatesets(
        'headerinclude',
        '#' . preg_quote('{$stylesheets}') . '#i',
        '<script type="text/javascript" src="{$mybb->asset_url}/jscripts/xem_fast_rep.js"></script>
{$stylesheets}'
    );
}

function xem_fast_rep_deactivate()
{
    include_once MYBB_ROOT."inc/adminfunctions_templates.php";

    find_replace_templatesets(
        'postbit',
        '#' . preg_quote('{$post[\'xem_fast_rep\']}
    {$post[\'attachments\']}') . '#i',
        '{$post[\'attachments\']}'
    );

    find_replace_templatesets(
        'postbit_classic',
        '#' . preg_quote('{$post[\'xem_fast_rep\']}
                    {$post[\'attachments\']}') . '#i',
        '{$post[\'attachments\']}'
    );

    find_replace_templatesets(
        'headerinclude',
        '#' . preg_quote('<script type="text/javascript" src="{$mybb->asset_url}/jscripts/xem_fast_rep.js"></script>
{$stylesheets}') . '#i',
        '{$stylesheets}'
    );
}

class xem_fast_rep
{

    public static $reputations;
    public static $rid = 0;
    public static $first_check = false;

    public function in_post(&$post)
    {
        global $db, $mybb, $pids;

        if(!self::$first_check)
        {
            if(!isset($pids))
            {
                $pids = "pid IN (".(int)$mybb->input['pid'].")";
            }

            self::get_reps($pids);
            self::$first_check = true;
        }

        $r = 0;

        if(isset(self::$reputations[ $post['pid'] ]))
        {
            foreach(self::$reputations[ $post['pid'] ] as $reputation)
            {
                $r += $reputation['reputation'];

                $liked_this[ $post['pid'] ][] = [
                    'username'   => $reputation['username'],
                    'uid'        => $reputation['uid'],
                    'reputation' => $reputation['reputation'],
                    'adduid'     => $reputation['adduid'],
                ];

                if($mybb->user['uid'] == $reputation['adduid'])
                {
                    $adduid = $reputation['adduid'];
                    $rep_value = $reputation['reputation'];
                }

            }
        }

        $add_reps = null;
        $delete_reps = null;
        if($mybb->user['uid'] != $post['uid'] && $mybb->user['uid'])
        {
            if(isset($adduid) && $adduid != $mybb->user['uid'] || $rep_value != 1)
            {
                $to_plus_rep = 1;
                if(isset($rep_value) && $rep_value == -1) $to_plus_rep = 0;
                $add_reps = self::add_button($post['uid'], $post['pid'], $to_plus_rep, '1');
            }

            if(isset($adduid) && $adduid != $mybb->user['uid'] || $rep_value != -1)
            {
                $to_minus_rep = -1;
                if(isset($rep_value) && $rep_value == 1) $to_minus_rep = 0;
                $delete_reps = self::add_button($post['uid'], $post['pid'], $to_minus_rep, '-1');
            }
        }

        $liked = self::liked_this($liked_this[ $post['pid'] ]);

        $post_reps = '<span id=\"xem_fast_rep\" class=\"reps_'.$post['pid'].'\" style=\"float:right;\">'.$liked.$add_reps.$delete_reps.self::count_reps($post['pid'], $r).'</span>';

        if(self::adduser_permissions() && self::getuser_permissions($post))
        {
            eval("\$post['xem_fast_rep'] = \"".$post_reps."\";");
            return $post;
        }
    }

    public function xmlhttp()
    {
        global $mybb, $db;

        if($mybb->input['action'] == 'xem_fast_rep' && (
            !$mybb->input['uid'] ||
            !$mybb->input['pid'] ||
            !$mybb->user['uid'] ||
            (int)$mybb->input['reputation'] != '1' &&
            (int)$mybb->input['reputation'] != '0' &&
            (int)$mybb->input['reputation'] != '-1'
        )) exit;

        $reputation = (int)$mybb->input['reputation'];
        $uid = (int)$mybb->input['uid'];
        $pid = (int)$mybb->input['pid'];

        if(!self::getuser_permissions($pid)) exit;
        if(!self::adduser_permissions()) exit;

        switch($mybb->input['action'])
        {

            case 'xem_fast_rep':

                $existing_reputation = self::existing_reputation($pid, $uid);

                self::$rid = $existing_reputation['rid'];

                if(!self::$rid && $reputation != 0)
                {
                    self::add([
                        'uid'        => $uid,
                        'adduid'     => (int)$mybb->user['uid'],
                        'pid'        => $pid,
                        'reputation' => $reputation,
                        'comments'   => ""
                    ]);

                    if($reputation == 1)
                    {
                        die(
                            stripslashes(self::liked_this($pid)) .
                            stripslashes(self::add_button($uid, $pid, '0', '-1')) .
                            stripslashes(self::count_reps($pid, self::get_count_reps($pid)))
                        );
                    }
                    else
                    {
                        die(
                            stripslashes(self::liked_this($pid)) .
                            stripslashes(self::add_button($uid, $pid, '0', '1')) .
                            stripslashes(self::count_reps($pid, self::get_count_reps($pid)))
                        );
                    }
                }
                elseif($reputation == 0 && self::$rid)
                {
                    self::delete(self::$rid, $uid);
                    die(
                        stripslashes(self::liked_this($pid)) .
                        stripslashes(self::add_button($uid, $pid, '1', '1')) .
                        stripslashes(self::add_button($uid, $pid, '-1', '-1')) .
                        stripslashes(self::count_reps($pid, self::get_count_reps($pid)))
                    );
                }
                elseif($existing_reputation['reputation'] != $reputation)
                {
                    self::update([
                        'uid'        => $uid,
                        'adduid'     => (int)$mybb->user['uid'],
                        'pid'        => $pid,
                        'reputation' => $reputation,
                        'comments'   => ""
                    ]);
                    die;
                }
                else
                {
                    die;
                }

            break;
        }
    }

    private static function get_reps($pids)
    {
        global $db;

        $get_reps = $db->query("SELECT
            r.adduid, r.pid, r.reputation, u.uid, u.username
            FROM ".TABLE_PREFIX."reputation r
            LEFT JOIN ".TABLE_PREFIX."users u ON (r.adduid = u.uid) WHERE ".$pids
        );

        while($rep = $db->fetch_array($get_reps))
        {
            self::$reputations[ $rep['pid'] ][] = [
                'adduid'     => $rep['adduid'],
                'reputation' => $rep['reputation'],
                'username'   => $rep['username'],
                'uid'        => $rep['uid'],
            ];
        }
    }

    private static function add($data)
    {
        global $db;

        $data['dateline'] = TIME_NOW;

        $db->insert_query('reputation', $data);

        $query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='".$data['uid']."'");
        $reputation_value = $db->fetch_field($query, "reputation_count");

        $db->update_query("users", ['reputation' => (int)$reputation_value], "uid='".$data['uid']."'");
    }

    private static function update($data)
    {
        global $db;

        $db->update_query('reputation', $data, 'rid = '.self::$rid);

        $query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='".$data['uid']."'");
        $reputation_value = $db->fetch_field($query, "reputation_count");

        $db->update_query("users", ['reputation' => (int)$reputation_value], "uid='".$data['uid']."'");
    }

    private static function delete($rid, $uid)
    {
        global $db;

        $db->delete_query('reputation', 'rid='.$rid);

        $query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='".$uid."'");
        $reputation_value = $db->fetch_field($query, "reputation_count");

        $db->update_query("users", ['reputation' => (int)$reputation_value], "uid='".$uid."'");
    }

    private static function existing_reputation($pid, $uid)
    {
        global $mybb, $db;

        $query = $db->simple_select("reputation", "*", "adduid='".$mybb->user['uid']."' AND uid='".$uid."' AND pid = '".$pid."'");
        $existing_reputation = $db->fetch_array($query);

        return $existing_reputation;
    }

    private static function add_button($uid, $pid, $to_rep, $rep_value = 1)
    {
        global $mybb, $lang;

        $lang -> load('xem_fast_rep');

        if(($mybb->settings['posrep'] && $to_rep == 1) || ($to_rep == 0 && $rep_value == 1))
        {
            return '<span onclick=\"vote(\''.$uid.'\', \''.$pid.'\', \''.$to_rep.'\')\" class=\"reps plus\" id=\"rep_plus_'.$pid.'\" title=\"'.$lang->xem_fast_rep_like_it.'\">+</span>';
        }

        if(($mybb->settings['negrep'] && $to_rep == -1) || $to_rep == 0)
        {
            return '<span onclick=\"vote(\''.$uid.'\', \''.$pid.'\', \''.$to_rep.'\')\" class=\"reps minus\" id=\"rep_minus_'.$pid.'\" title=\"'.$lang->xem_fast_rep_unlike_it.'\">-</span>';
        }
    }

    private static function count_reps($pid, $count)
    {
        global $lang;

        $lang -> load('xem_fast_rep');

        return '<span class=\"reps likes_'.$pid.'\" title=\"'.$lang->xem_fast_rep_who_like_it.'\">'.$count.'</span>';
    }

    private static function get_count_reps($pid)
    {
        global $db;

        $c = 0;
        $counts = $db->simple_select("reputation", "reputation", "pid='".$pid."'");
        while($count = $db->fetch_array($counts))
        {
            $c += $count['reputation'];
        }

        return $c;
    }

    private static function liked_this($liked_this)
    {
        global $mybb, $db, $lang;

        $lang -> load('xem_fast_rep');

        if($mybb->settings['xem_fast_rep_show_liked_this'])
        {
            if(is_array($liked_this))
            {
                $num = 1;
                foreach($liked_this as $liked)
                {
                    if($liked['reputation'] == 1)
                    {
                        if($mybb->user['uid'] == $liked['uid']) $num = 0;

                        $likeThis[$num] = [
                            $liked['uid'],
                            $liked['username'],
                        ];
                        $num++;
                    }
                }
            }
            elseif($liked_this !== NULL)
            {
                $get_likes = $db->query("SELECT u.username, u.uid
                    FROM ".TABLE_PREFIX."reputation r
                    LEFT JOIN ".TABLE_PREFIX."users u ON (r.adduid = u.uid)
                    WHERE r.pid = '".$liked_this."' AND r.reputation = '1'"
                );

                $num = 1;
                while($liked = $db->fetch_array($get_likes))
                {
                    if($mybb->user['uid'] == $liked['uid']) $num = 0;
                    $likeThis[$num] = [
                        $liked['uid'],
                        $liked['username'],
                    ];
                    $num++;
                }
            }

            $count_likeThis = count($likeThis);
            if(is_array($likeThis))
            {
                foreach($likeThis as $key => $lt)
                {
                    if(in_array($mybb->user['username'], $lt))
                    {
                        $likeThis[$key] = array_replace($lt, [1 => $lang->you]);
                        $youLikeThis = true;
                    }
                }

                ksort($likeThis);
                $likeThis = array_values($likeThis);

                switch($count_likeThis)
                {
                    case 1:
                        if(isset($youLikeThis))
                        {
                            $m = $lang->you_like_it;
                        }
                        else
                        {
                            $m = self::profile_url($likeThis[0][0], $likeThis[0][1]).' '.$lang->like_it;
                        }
                        break;
                    case 2:
                        $m = self::profile_url($likeThis[0][0], $likeThis[0][1]).' i '.self::profile_url($likeThis[1][0], $likeThis[1][1]).' '.$lang->like_it;
                        break;
                    case 3:
                        $m = self::profile_url($likeThis[0][0], $likeThis[0][1]).', '.self::profile_url($likeThis[1][0], $likeThis[1][1]).' i '.self::profile_url($likeThis[2][0], $likeThis[2][1]).' '.$lang->like_it;
                        break;
                    default:
                        $m = self::profile_url($likeThis[0][0], $likeThis[0][1]).', '.self::profile_url($likeThis[1][0], $likeThis[1][1]).', '.self::profile_url($likeThis[2][0], $likeThis[2][1]).' '.$lang->and.' '.($count_likeThis-3).' '.$lang->others_person_like_it;
                        break;
                }
                return '<span class=\"liked_this\">'.$m.'</span>';
            }
        }
    }

    private static function profile_url($uid, $username)
    {
        return '<a href=\"'.$mybb->settings['bburl'].get_profile_link($uid).'\">'.$username.'</a>';
    }

    private static function adduser_permissions()
    {
        global $mybb;

        if($mybb->usergroup['canview'] != 1 || $mybb->usergroup['cangivereputations'] != 1)
        {
            return false;
        }

        return true;
    }

    private static function getuser_permissions($post)
    {
        global $mybb, $db;

        if(!is_array($post))
        {
            $get_post = $db->simple_select('posts', '*', 'pid='.$post);
            $post = $db->fetch_array($get_post);
        }

        $user = get_user($post['uid']);
        $user_permissions = user_permissions($uid);

        if($post)
        {
            $thread = get_thread($post['tid']);
            $forum = get_forum($thread['fid']);
            $forumpermissions = forum_permissions($forum['fid']);

            if(($post['visible'] == 0 && !is_moderator($forum['fid'], "canviewunapprove")) || $post['visible'] < 0)
            {
                $permissions = false;
            }
            elseif(($thread['visible'] == 0 && !is_moderator($forum['fid'], "canviewunapprove")) || $thread['visible'] < 0)
            {
                $permissions = false;
            }
            elseif($forumpermissions['canview'] == 0 || $forumpermissions['canpostreplys'] == 0 || $mybb->user['suspendposting'] == 1)
            {
                $permissions = false;
            }
            elseif(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
            {
                $permissions = false;
            }
            else
            {
                $permissions = true;
            }
        }
        else
        {
            $permissions = false;
        }

        if($user_permissions['usereputationsystem'] != 1 || !$user || !$permissions)
        {
            return false;
        }

        return true;
    }

}
