<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Extra validation rules for CRUD generator modules
| -------------------------------------------------------------------------
| Every field in Admin -> Generator can be given rules: pick CodeIgniter's
| own (alpha_dash, min_length, valid_url, ...), type a regex pattern, or
| choose one registered here. Registered rules are plain PHP, so they can
| say anything; they show up in the builder's rule list under "Custom rules
| from code". A module can ship its own in
| application/modules/<module>/config/crud_rules.php (same format).
|
|   $config['crud_rules']['name'] = array(
|       'label'   => 'Shown in the builder',
|       'param'   => 'none',            // none | number | text : what the builder asks for
|       'types'   => array('text'),     // input types it can be used on: text, textarea,
|                                       //   number, email, date  (default: all five)
|       'message' => 'The {field} field ... {param} ...',   // used when the rule returns false
|       'rule'    => function ($value, $param, array $input) { ... },
|   );
|
| The rule is only called for a non-empty value (whether a field is
| required is its own checkbox). Return true when the value is fine, false
| to fail with 'message', or a string to fail with that message. {field} is
| the field's label and {param} the parameter. $input holds everything that
| was posted, for rules that compare fields. The key is stored in the module
| definition, so do not rename it once a module uses it.
*/

// Indonesian mobile number: 0812-3456-7890, +62 812 3456 7890, 6281234567890 ...
$config['crud_rules']['phone_id'] = array(
	'label' => 'Indonesian mobile number',
	'param' => 'none',
	'types' => array('text'),
	'message' => 'The {field} field must be an Indonesian mobile number, e.g. 0812-3456-7890.',
	'rule' => static function ($value) {
		return (bool) preg_match('/^(\+62|62|0)8[1-9][0-9]{6,10}$/', preg_replace('/[\s\-().]/', '', $value));
	},
);

// Takes a parameter: starts_with[INV-]
$config['crud_rules']['starts_with'] = array(
	'label' => 'Starts with ...',
	'param' => 'text',
	'types' => array('text'),
	'message' => 'The {field} field must start with {param}.',
	'rule' => static function ($value, $param) {
		return strncmp($value, $param, strlen($param)) === 0;
	},
);

// Returning a string gives a message that depends on the value.
$config['crud_rules']['not_in_past'] = array(
	'label' => 'Date today or later',
	'param' => 'none',
	'types' => array('date'),
	'rule' => static function ($value) {
		return $value >= date('Y-m-d') ? true : 'The {field} field cannot be before today ('.date('Y-m-d').').';
	},
);
