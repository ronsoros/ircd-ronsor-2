<?php

class Channel {

var $id;
var $name;
var $created = 0;
var $users = array();
var $modes = array();
var $bans = array();
var $excepts = array();
var $invex = array();
var $topic = "";
var $topic_setby = "";
var $topic_seton = 0;

function __construct($id, $name){
    $this->id = $id;
    $this->name = $name;
    $this->created = time();
}

function addUser(&$user, $mode=false){
    $this->users[$user->id] = true;
    if($mode)
        $this->setModes($user, '+'.$mode." {$user->nick}");
}

function getModes(){
    global $ircd;
    $modes = '+';
    $extra = array();
    foreach($this->modes as $m=>$e){
        if(is_array($e))
            continue;
        $modes .= "$m";
    }
    return $modes.' '.implode(' ', $extra);
}

function getUserPrefix($user){
    if(@in_array($user->nick, @$this->modes['q']))
        return '~';
    if(@in_array($user->nick, @$this->modes['a']))
        return '&';
    if(@in_array($user->nick, @$this->modes['O']))
        return '@';
    if(@in_array($user->nick, @$this->modes['o']))
        return '@';
    if(@in_array($user->nick, @$this->modes['h']))
        return '%';
    if(@in_array($user->nick, @$this->modes['v']))
        return '+';
    return '';
}

function hasMode($m, $t=false){
    global $ircd;
    if(isset($this->modes[$m]))
        if($ircd->chanModes[$m]->type == 'array')
            return in_array($t, $this->modes[$m]);
        else
            return true;
    return false;
}

function hasVoice($user){
    return ($this->hasMode('v', $user->nick) || $this->isOwner($user) || $this->isAop($user) || $this->isOp($user) || $this->isHop($user));
}

function isAop($user){
    return ($this->hasMode('a', $user->nick) || $this->isOwner($user));
}

function isBanned($user){
    return false;
}

function isHop($user){
    return $this->hasMode('h', $user->nick);
}

function isOp($user){
    return ($this->hasMode('o', $user->nick) || $this->hasMode('O', $user->nick) || $this->isAop($user) || $this->isOwner($user));
}

function isOwner($user){
    return $this->hasMode('q', $user->nick);
}

function nick($user, $oldnick){
    foreach($this->modes as &$m)
        if(is_array($m))
            foreach($m as &$n)
                $n = ($n == $oldnick?$user->nick:$n);
}

function removeUser($user){
    if(array_key_exists($user->id, $this->users))
        unset($this->users[$user->id]);
}

function send($msg, $excl=""){
    global $ircd;
    foreach($this->users as $id=>$m){
        if(is_object($excl))
            if($excl->id == $id)
                continue;
        $ircd->_clients[$id]->send($msg);
    }
}

function setModes($user, $mask){
    global $ircd;
    $parts = explode(" ", $mask);
    $mask = str_split($parts['0']);
    array_shift($parts);
    $act = $add = $take = $extra = "";
    foreach($mask as $c){
        if($c == '+' || $c == '-'){
            $act = $c;
            continue;
        }
        if(!array_key_exists($c, $ircd->chanModes)){
            $ircd->error(472, $user, $c);
            continue;
        }   
        if($act == '+'){
            if(@$ircd->chanModes[$c]->extra==true && !isset($parts['0'])){
                continue;
            }
            if(isset($ircd->chanModes[$c]->hooks['set'])){
                $d = array('user'=>&$user, 'chan'=>&$this, 'extra'=>@$parts['0']);
                if(!$ircd->chanModes[$c]->hooks['set'](&$d)){
                    $ircd->error($d['errno'], $user, @$d['errstr']);
                    continue;
                }
            }
            if(@$ircd->chanModes[$c]->extra==true && isset($parts['0'])){
                if(@$ircd->chanModes[$c]->type == 'array'){
                    $as = array_shift($parts);
                    $extra .= ' '.$as;
                    $this->modes[$c][] = $as;
                    $add .= $c;
                } else {
                    $as = array_shift($parts);
                    $extra .= $as;
                    $this->modes[$c] = $as;
                    $add .= $c;
                }
            } elseif(isset($ircd->chanModes[$c])){
                $this->modes[$c] = true;
                $add .= $c;
            }
        } else {
            if(isset($ircd->chanModes[$c]->hooks['unset'])){  
                $d = array('user'=>&$user, 'chan'=>&$this, 'extra'=>@$parts['0']);
                if(!$ircd->chanModes[$c]->hooks['unset'](&$d)){
                    $ircd->error($d['errno'], $user, @$d['errstr']);
                    continue;
                }
            }
            if($ircd->chanModes[$c]->extra==true && @$ircd->chanModes[$c]->type == 'array'){
                $k = array_search(current($parts), $this->modes[$c]);
                if($k !== FALSE){
                    unset($this->modes[$c][$k]);
                    $extra .= ' '.array_shift($parts);
                    $take .= $c;
                }
            } else {
                unset($this->modes[$c]);
                $take .= $c;
            }
        }
    }
    if($add.$take != "")
        $this->send(":{$user->prefix} MODE $this->name ".(!empty($add)?"+$add":'').(!empty($take)?"-$take":'').(!empty($extra)?$extra:''));
}

function setTopic($user, $msg){
    $this->topic = $msg;
    $this->topic_setby = $user->nick;
    $this->topic_seton = time();
}

function userInChan($nick){
    global $ircd;
    $u = $ircd->getUserByNick($nick);
    if(!$u)
        return false;
    if(isset($this->users[$u->id]))
        return true;
    return false;
}

}

?>
