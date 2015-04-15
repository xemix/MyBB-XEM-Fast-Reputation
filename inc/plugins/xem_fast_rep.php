<?php
/**
 * Author: Szczepan 'Xemix' Machaj
 * WWW: xemix.eu
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

if(THIS_SCRIPT == 'showthread.php')
{
    global $templatelist;
    $templatelist .= !empty($templatelist)
        ? ',xem_fast_rep,xem_fast_rep_post_positive,xem_fast_rep_post_negative,xem_fast_rep_post_reps_count,xem_fast_rep_post_who_like_it'
        : 'xem_fast_rep,xem_fast_rep_post_positive,xem_fast_rep_post_negative,xem_fast_rep_post_reps_count,xem_fast_rep_post_who_like_it';
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
        'version'       => '1.5',
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
        [   
            'name'        => 'xem_fast_rep_allow_unlike_posts',
            'title'       =>  $lang->xem_fast_rep_allow_unlike_posts,
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

    $xfr_post_reps = '<span id="xem_fast_rep" class="reps_{$post[\'pid\']}" style="float:right;">{$post_reps}</span>';
    $xfr_post_positive = '<span onclick="vote(\'{$uid}\', \'{$pid}\', \'{$to_rep}\')" class="reps plus" id="rep_plus_{$pid}" title="{$lang->xem_fast_rep_like_it}">+</span>';
    $xfr_post_negative = '<span onclick="vote(\'{$uid}\', \'{$pid}\', \'{$to_rep}\')" class="reps minus" id="rep_minus_{$pid}" title="{$lang->xem_fast_rep_unlike_it}">-</span>';
    $xfr_post_reps_count = '<span class="reps likes_{$pid}{$color}" title="{$lang->xem_fast_rep_who_like_it}">{$count}</span>';
    $xfr_post_who_like_it = '<span class="liked_this">{$liked_this}</span>';

    $insert_templates = [
        [
            'title' => 'xem_fast_rep',
            'template' => $db->escape_string($xfr_post_reps),
            'sid' => '-1',
            'version' => '1',
            'dateline' => NOW
        ],
        [
            'title' => 'xem_fast_rep_post_positive',
            'template' => $db->escape_string($xfr_post_positive),
            'sid' => '-1',
            'version' => '1',
            'dateline' => NOW
        ],
        [
            'title' => 'xem_fast_rep_post_negative',
            'template' => $db->escape_string($xfr_post_negative),
            'sid' => '-1',
            'version' => '1',
            'dateline' => NOW
        ],
        [
            'title' => 'xem_fast_rep_post_reps_count',
            'template' => $db->escape_string($xfr_post_reps_count),
            'sid' => '-1',
            'version' => '1',
            'dateline' => NOW
        ],
        [
            'title' => 'xem_fast_rep_post_who_like_it',
            'template' => $db->escape_string($xfr_post_who_like_it),
            'sid' => '-1',
            'version' => '1',
            'dateline' => NOW
        ]
    ];

    $db->insert_query_multiple('templates', $insert_templates);

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

    $db->delete_query("templates", "title = 'xem_fast_rep'");
    $db->delete_query("templates", "title = 'xem_fast_rep_post_positive'");
    $db->delete_query("templates", "title = 'xem_fast_rep_post_negative'");
    $db->delete_query("templates", "title = 'xem_fast_rep_post_reps_count'");
    $db->delete_query("templates", "title = 'xem_fast_rep_post_who_like_it'");

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
        global $db, $mybb, $templates, $pids, $thread;

        if(!self::$first_check) 
        {
            if(!isset($pids) || $pids == '')
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

        if($mybb->settings['xem_fast_rep_allow_unlike_posts'] == 0) 
        {
            if(is_array($liked_this[ $post['pid'] ]))
            {
                if(self::checkLiked($liked_this[$post['pid']], 1)) 
                {
                    $delete_reps = null;
                }
                if(self::checkLiked($liked_this[$post['pid']], -1)) 
                {
                    $add_reps = null;
                }
            }
        }

        $liked = self::liked_this($liked_this[ $post['pid'] ]);

        $post_reps = $liked.$add_reps.$delete_reps.self::count_reps($post['pid'], $r);
        $xfr_post_reps = $templates->get("xem_fast_rep");

        if(self::adduser_permissions() && self::getuser_permissions($post, $thread))
        {
            eval('$post[\'xem_fast_rep\'] = "' . $xfr_post_reps . '";');
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
        ) && !self::getuser_permissions($mybb->input['pid']) &&
            !self::adduser_permissions()
        ) exit;

        $reputation = (int)$mybb->input['reputation'];
        $uid = (int)$mybb->input['uid'];
        $pid = (int)$mybb->input['pid'];

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
                        'comments'   => '',
                    ]);

                    if($reputation == 1)
                    {
                        die(
                            stripslashes(self::liked_this($pid)) .
                            ($mybb->settings['xem_fast_rep_allow_unlike_posts'] == 1 ? stripslashes(self::add_button($uid, $pid, '0', '-1')) : null) .
                            stripslashes(self::count_reps($pid, self::get_count_reps($pid)))
                        );
                    }
                    else
                    {
                        die(
                            stripslashes(self::liked_this($pid)) .
                            ($mybb->settings['xem_fast_rep_allow_unlike_posts'] == 1 ? stripslashes(self::add_button($uid, $pid, '0', '1')) : null) .
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
                        'comments'   => '',
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
        global $mybb, $lang, $templates;

        $lang -> load('xem_fast_rep');

        if(($mybb->settings['posrep'] && $to_rep == 1) || ($to_rep == 0 && $rep_value == 1))
        {
            eval('$xfr_pos = "' . $templates->get("xem_fast_rep_post_positive") . '";');
            return $xfr_pos;
        }

        if(($mybb->settings['negrep'] && $to_rep == -1) || $to_rep == 0)
        {
            eval('$xfr_neg = "' . $templates->get("xem_fast_rep_post_negative") . '";');
            return $xfr_neg;
        }
    }

    private static function count_reps($pid, $count)
    {
        global $templates, $lang;

        $lang -> load('xem_fast_rep');

        if($count > 0)
        {
            $color = ' positives';
        }
        elseif($count < 0)
        {
            $color = ' negatives';
        }
        else
        {
            $color = null;
        }

        eval('$count_reps = "' . $templates->get("xem_fast_rep_post_reps_count") . '";');
        return $count_reps;
    }

    private static function get_count_reps($pid)
    {
        global $db;

        $counts = $db->simple_select("reputation", "SUM(reputation)", "pid='".$pid."'");
        $count = $db->fetch_array($counts);

        return ($count['SUM(reputation)'] != null ? $count['SUM(reputation)'] : 0);
    }

    private static function liked_this($liked_this)
    {
        global $mybb, $templates, $db, $lang;

        $lang -> load('xem_fast_rep');

        if($mybb->settings['xem_fast_rep_show_liked_this'] == 1)
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

                $liked_this = $m;
                eval('$liked_this_tpl = "' . $templates->get("xem_fast_rep_post_who_like_it") . '";');
                return $liked_this_tpl;
            }
        }
    }

    private static function profile_url($uid, $username)
    {
        return '<a href="'.$mybb->settings['bburl'].get_profile_link($uid).'">'.$username.'</a>';
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

    private static function getuser_permissions($post, $thr=false)
    {
        global $mybb, $db;

        if(!is_array($post))
        {
            $get_post = $db->simple_select('posts', '*', 'pid='.(int)$post);
            $post = $db->fetch_array($get_post);
        }

        $user_permissions = user_permissions($post['uid']);

        if($post)
        {
            $thread = is_array($thr) ? $thr : get_thread($post['tid']); 
            $forumpermissions = forum_permissions($post['fid']);

            if(($post['visible'] == 0 && !is_moderator($post['fid'], "canviewunapprove")) || $post['visible'] < 0)
            {
                return false;
            }
            elseif(($thread['visible'] == 0 && !is_moderator($post['fid'], "canviewunapprove")) || $thread['visible'] < 0)
            {
                return false;
            }
            elseif($forumpermissions['canview'] == 0 || $forumpermissions['canpostreplys'] == 0 || $mybb->user['suspendposting'] == 1)
            {
                return false;
            }
            elseif(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
            {
               return false;
            }
        }
        else
        {
            return false;
        }

        if($user_permissions['usereputationsystem'] != 1 || $post['uid'] == 0)
        {
            return false;
        }

        return true;
    }

    private static function checkLiked($liked, $repValue)
    {
        global $mybb;

        if(!is_array($liked)) return false;

        foreach($liked as $like)
        {
            if(isset($like['adduid']) && $like['adduid'] == $mybb->user['uid'] && $like['reputation'] == $repValue) 
            {
                return true;
            }
        }

        return false;
    }

}