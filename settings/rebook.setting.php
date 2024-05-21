<?php
declare(strict_types = 1);

use CRM_Rebook_ExtensionUtil as E;

return [
  'rebook_allow_households' => [
    'name' => 'rebook_allow_households',
    'type' => 'Boolean',
    'title' => E::ts('Allow Households'),
    'description' => E::ts('Allow households to be target contacts.'),
    'default' => FALSE,
    'html_type' => 'checkbox',
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => [E::SHORT_NAME => ['weight' => 10]],
  ],
];
