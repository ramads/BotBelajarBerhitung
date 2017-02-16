<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Model_berhitung extends CI_Model
{
    function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    function log_events($signature, $body)
    {
        $this->db->set('signature', $signature)
            ->set('events', $body)
            ->insert('eventlog');

        return $this->db->insert_id();
    }

    function getUser($userId)
    {
        $data = $this->db->where('user_id', $userId)->get('users')->row_array();
        if (count($data) > 0) return $data;
        return false;
    }

    function saveUser($profile)
    {
        $this->db->set('user_id', $profile['userId'])
            ->set('display_name', $profile['displayName'])
            ->insert('users');
        $this->createLogUser($profile['userId']);
        return $this->db->insert_id();
    }

    function createLogUser($userId)
    {
        $this->db->set('user_id', $userId)
            ->insert('level');
        $this->db->set('user_id', $userId)
            ->insert('log');
    }

    function getQuestion($userId)
    {
        $userLog = $this->getUserLog($userId);
        $sessionName = $this->getSessionName($userLog['session']);
        $level = $userLog[$sessionName];
        $question = "";
        $answer = 0;
        switch ($userLog['session']) {
            case 1:
                $a = rand(1, $level * 20);
                $b = rand(1, $level * 20);
                $answer = $a + $b;
                $question = "" . $a . " + " . $b;
                break;
            case 2:
                $a = rand(1, $level * 20);
                $b = rand(1, $level * 20);
                if ($b > $a) {
                    list($a, $b) = array($b, $a);
                }
                $answer = $a - $b;
                $question = "" . $a . " - " . $b;
                break;
            case 3:
                $a = rand($level, $level * 2 + 2);
                $b = rand($level, $level * 2 + 2);
                $answer = $a * $b;
                $question = "" . $a . " x " . $b;
                break;
            default:
                return false;
        }
        $this->saveAnswer($userId, $sessionName, $answer);
        return $question;
    }

    function isAnswerEqual($userId, $userAnswer)
    {
        $userLog = $this->getUserLog($userId);
        $sessionName = $this->getSessionName($userLog['session']);
        $result = [
            'isEqual'       => ($userLog[$sessionName . '_answer'] == $userAnswer),
            'session'       => $this->getSessionName($userLog['session']),
            'answer'        => $userLog[$sessionName . '_answer'],
            'level'         => $userLog[$sessionName],
            'levelUpdated'  => false
        ];
        if ($result['isEqual']) {
            $counter = $userLog[$sessionName . '_counter'] + 1;
            if ($counter == 5) {
                $this->updateLevel($userId);
                $this->updateCounter($userId, $sessionName, 0);
                $result['levelUpdated'] = true;
            } else {
                $this->updateCounter($userId, $sessionName, $counter);
            }
        }
        return $result;
    }

    function updateCounter($userId, $sessionName, $newCounter)
    {
        $this->db->set($sessionName . '_counter', $newCounter)
            ->where('user_id', $userId)
            ->update('log');
    }

    function getUserLevel($userId)
    {
        $userLog = $this->getUserLog($userId);
        return $userLog[$this->getSessionName($userLog['session'])];
    }

    function updateLevel($userId)
    {
        $userLog = $this->getUserLog($userId);
        $session = $this->getSessionName($userLog['session']);
        $newLevel = $userLog[$session] + 1;

        // insert new level
        $this->db->set($session, $newLevel)
            ->where('user_id', $userId)
            ->update('level');
    }

    function getUserLog($userId)
    {
        return $this->db->select('*')
            ->from('log lo')
            ->join('level le', 'le.user_id=lo.user_id', 'left')
            ->where('lo.user_id', $userId)
            ->get()->row_array();
    }

    function getSessionName($sessionId)
    {
        switch ($sessionId) {
            case 1:
                return "penjumlahan";
                break;
            case 2:
                return "pengurangan";
                break;
            case 3:
                return "perkalian";
                break;
            default:
                return false;
                break;
        }
    }

    function getSessonId($sessionName)
    {
        switch ($sessionName) {
            case "penjumlahan":
                return 1;
                break;
            case "pengurangan":
                return 2;
                break;
            case "perkalian":
                return 3;
                break;
            default:
                return false;
                break;
        }
    }

    function saveAnswer($userId, $session, $ans)
    {
        $this->db->set($session . "_answer", $ans)
            ->where('user_id', $userId)
            ->update('log');
    }

    function getLastSession($userId)
    {
        $log = $this->db->select('session')
            ->where('user_id', $userId)
            ->get('log')
            ->row_array();
        return $this->getSessionName($log['session']);
    }

    function setSession($userId, $sessionName)
    {
        $this->db->set('session', $this->getSessonId($sessionName))
            ->where('user_id', $userId)
            ->update('log');
    }

    function getUserState($userId) {
        $row = $this->db->select('state')
            ->where('user_id', $userId)
            ->get('users')->row_array();
        return $row['state'];
    }

    function setUserState($userId, $state)
    {
        $this->db->set('state', $state)
            ->where('user_id', $userId)
            ->update('users');
    }
}