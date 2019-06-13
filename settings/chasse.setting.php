<?php
return [
  'chasse_config' => [
    'group_name'  => 'Chasse Config',
    'group'       => 'chasse',
    'name'        => 'chasse_config',
    'type'        => 'Array',
    'default'     => [],
    'description' => 'JSON defining chasse plans',
    'help_text'   => '',
    'is_domain'   => 1,
    'is_contact'  => 0,
  ],
  'chasse_locks' => [
    'group_name'  => 'Chasse Locks',
    'group'       => 'chasse',
    'name'        => 'chasse_locks',
    'type'        => 'Array',
    'default'     => [],
    'description' => 'Flags that chasse is processing.',
    'help_text'   => '',
    'is_domain'   => 1,
    'is_contact'  => 0,
  ]
];
