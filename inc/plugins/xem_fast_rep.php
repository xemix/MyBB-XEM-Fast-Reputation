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
        'version'       => '1.1',
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
        'title'       =>  $lang->xem_fast_rep_settings_title,
        'description' =>  $lang->xem_fast_rep_settings_title,
    ]);
    
    $settings = [
        [   
            'name'        => 'xem_fast_rep_active',
            'title'       =>  $lang->xem_fast_rep_plugin_active,
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
            self::get_reps($pids);
            self::$first_check = true;
        }

        $r = 0;
        
        if(isset(self::$reputations[ $post['pid'] ]))
        {
            foreach(self::$reputations[ $post['pid'] ] as $reputation)
            {
                $r += $reputation['reputation']; //suma reputacji postu

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

        $post_reps = '<span class=\"reps_'.$post['pid'].'\" style=\"float:right;\">'.$add_reps.$delete_reps.self::count_reps($post['pid'], $r).'</span>';

        eval("\$post['xem_fast_rep'] = \"".$post_reps."\";");
        return $post;
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
        )) exit();

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
                    ]);

                    if($reputation == 1)
                    {
                        die(
                            stripslashes(self::add_button($uid, $pid, '0', '-1')) .
                            stripslashes(self::count_reps($pid, self::get_count_reps($pid)))
                        );
                    }
                    else
                    {
                        die(
                            stripslashes(self::add_button($uid, $pid, '0', '1')) .
                            stripslashes(self::count_reps($pid, self::get_count_reps($pid)))
                        );
                    }
                }
                elseif($reputation == 0 && self::$rid)
                {
                    self::delete(self::$rid);
                    die(
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

        $get_reps = $db->simple_select("reputation", "adduid, pid, reputation", $pids);
        while($rep = $db->fetch_array($get_reps))
        {
            self::$reputations[ $rep['pid'] ][] = [
                'adduid'     => $rep['adduid'],
                'reputation' => $rep['reputation'],
            ];
        }
    }

    private static function add($data)
    {
        global $db;

        $data['dateline'] = TIME_NOW;

        $db->insert_query('reputation', $data);
    }

    private static function update($data)
    {
        global $db;

        $db->update_query('reputation', $data, 'rid = '.self::$rid);
    }

    private static function delete($rid)
    {
        global $db;

        $db->delete_query('reputation', 'rid='.$rid);
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

}