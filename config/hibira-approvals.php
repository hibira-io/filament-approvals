<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | The agent classes whose paused tool calls go to the inbox. Leave empty
    | to send every agent's. A class also matches its subclasses.
    |
    */

    'agents' => [],

    /*
    |--------------------------------------------------------------------------
    | Resuming
    |--------------------------------------------------------------------------
    |
    | Once every paused tool call has a decision, a queued job resumes the
    | agent. Set the connection and queue that job runs on. On the sync
    | queue, the reviewer's browser waits for the agent to finish.
    |
    */

    'queue' => [
        'connection' => env('HIBIRA_APPROVALS_QUEUE_CONNECTION'),
        'name' => env('HIBIRA_APPROVALS_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | How many characters a reviewer may write as a rejection reason, which
    | goes back to the model as the tool's result.
    |
    */

    'reason_characters' => 1000,

];
