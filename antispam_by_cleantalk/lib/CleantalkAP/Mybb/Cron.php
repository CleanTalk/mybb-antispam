<?php
/*
*	CleanTalk cron class
*   MyBB compatibility only
*	Version 1.0
*/

namespace CleantalkAP\Mybb;

class Cron
{
    public $tasks = array(); // Array with tasks
    public $tasks_to_run = array(); // Array with tasks which should be run now
    public $tasks_completed = array(); // Result of executed tasks

    // Currently selected task
    private $task;
    private $handler;
    private $period;
    private $next_call;

    // Option name with cron data
    const CRON_OPTION_NAME = 'antispam_by_cleantalk_cron';

    // Getting tasks option
    public function __construct()
    {
        $this->tasks = $this->getTasks();
    }

    public function getTasks()
    {
        global $db;

        $query = $db->simple_select('settings', 'value', "name='" . self::CRON_OPTION_NAME . "'");
        $tasks = $db->fetch_field($query, 'value');
        return empty($tasks) ? array() : json_decode( $tasks, true );
    }

    static public function updateDatabase( $tasks )
    {
        global $db;
        $antispam_by_cleantalk_cron = array(
            'value'	=> json_encode( $tasks )
        );
        $db->update_query("settings", $antispam_by_cleantalk_cron, "name='" . self::CRON_OPTION_NAME . "'" );
    }

    // Adding new cron task
    static public function addTask($task, $handler, $period, $first_call = null, $update = false)
    {
        global $mybb, $db;

        // First call time() + preiod
        $first_call = !$first_call ? time()+$period : $first_call;

        $tasks = $mybb->settings[self::CRON_OPTION_NAME];
        $tasks = empty($tasks) ? array() : json_decode( $tasks, true );

        if(isset($tasks[$task]) && !$update)
            return false;

        // Task entry
        $tasks[$task] = array(
            'handler' => $handler,
            'next_call' => $first_call,
            'period' => $period,
        );

        self::updateDatabase( $tasks );

        return true;
    }

    // Removing cron task
    static public function removeTask($task)
    {
        global $mybb, $db;

        $tasks = $mybb->settings[self::CRON_OPTION_NAME];
        $tasks = empty($tasks) ? array() : json_decode( $tasks, true );

        if(!isset($tasks[$task]))
            return false;

        unset($tasks[$task]);

        self::updateDatabase( $tasks );

        return true;
    }

    // Updates cron task, creates task if not exists
    static public function updateTask($task, $handler, $period, $first_call = null){
        self::addTask($task, $handler, $period, $first_call, true);
    }

    // Getting tasks which should be run. Putting tasks that should be run to $this->tasks_to_run
    public function checkTasks()
    {
        if(empty($this->tasks))
            return true;

        foreach($this->tasks as $task => $task_data){

            if($task_data['next_call'] <= time())
                $this->tasks_to_run[] = $task;

        }unset($task, $task_data);

        return $this->tasks_to_run;
    }

    // Run all tasks from $this->tasks_to_run. Saving all results to (array) $this->tasks_completed
    public function runTasks()
    {
        if(empty($this->tasks_to_run))
            return true;

        foreach($this->tasks_to_run as $task){

            $this->selectTask($task);

            if(function_exists($this->handler)){
                $this->tasks_completed[$task] = call_user_func($this->handler);
                $this->next_call =  time() + $this->period;
            }else{
                $this->tasks_completed[$task] = false;
            }

            $this->saveTask($task);

        }unset($task, $task_data);

        $this->saveTasks();

        return $this->tasks_completed;
    }

    // Select task in private properties for comfortable use.
    private function selectTask($task)
    {
        $this->task      = $task;
        $this->handler   = $this->tasks[$task]['handler'];
        $this->period    = $this->tasks[$task]['period'];
        $this->next_call = $this->tasks[$task]['next_call'];
    }

    // Save task in private properties for comfortable use
    private function saveTask($task)
    {
        $task                            = $this->task;
        $this->tasks[$task]['handler']   = $this->handler;
        $this->tasks[$task]['period']    = $this->period;
        $this->tasks[$task]['next_call'] = $this->next_call;
    }

    // Save option with tasks
    public function saveTasks()
    {
        self::updateDatabase( $this->tasks );
    }

    public function getDefaultTasks()
    {
        return [
            'sfw_update' =>
                array (
                    'handler' => 'antispam_by_cleantalk_sfw_update',
                    'next_call' => time() + 60,
                    'period' => 86400,
                ),
            'send_sfw_logs' =>
                array (
                    'handler' => 'antispam_by_cleantalk_sfw_send_logs',
                    'next_call' => time() + 3600,
                    'period' => 3600,
                ),
        ];
    }
}
