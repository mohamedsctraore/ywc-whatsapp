<?php

/**
 * Permissions
 *-----------------------------------------------------------------------------*/

return [
    'administrative' => [
        'title' => __tr('Administrative'),
        'description' => __tr('Allow/Deny permissions like Configuration, Subscription, Team Members etc'),
    ],
    'manage_contacts' => [
        'title' => __tr('Manage Contacts'),
        'description' => __tr('Allow/Deny access for Manage Contacts, Groups, Custom Contact Fields etc'),
    ],
    'manage_campaigns' => [
        'title' => __tr('Manage Campaigns'),
        'description' => __tr('Allow/Deny access like Creating, Executing and Scheduling Campaigns etc'),
    ],
    'messaging' => [
        'title' => __tr('Messaging'),
        'description' => __tr('Allow/Deny access like Chat, Manage Templates etc'),
    ],
    'manage_bot_replies' => [
        'title' => __tr('Manage Bot Replies'),
        'description' => __tr('Allow/Deny access for Bot Replies'),
    ],
];