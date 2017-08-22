<?php
use \Jacwright\RestServer\RestException;

/**
 * Test Controller for handling edge cases between formats
 * Many values are empty or false, in an effort to standardize
 * the appearance of these values across the spectrum of supported
 * output formats.
 *
 * Some formats have explicit test cases tailored towards their specific
 * behaviours, such as XML's assumption based upon "plural" keys.
 */
class FormatEdgeController {
	/**
	 * @name EmptyTest
	 * @description Returns a bunch of falsy/empty data
	 *    Additionally tests some XML plural handling with empty data.
	 *
	 * @url GET /
	 */
	public function emptyTest() {
		return array(
			'test'             => 'Empty Test',
			'int_zero'         => 0,
			'str_zero'         => "0",
			'bool_false'       => false,
			'str_null'         => null,
			'str_nulls'        => null,
			'empty_array'      => array(),
			'empty_arrays'     => array(),
			'array_null'       => array(null),
			'array_int_zero'   => array(0),
			'array_str_zero'   => array("0"),
			'array_bool_false' => array(false),
		);
	}

	/**
	 * @name XMLConsistancyTest
	 * @description Returns a bunch of test cases for XML assumptions.
	 *
	 * @url GET /xmlconsist
	 */
	public function XMLTest() {
		return array(
			'test'                   => 'XML Test',
			'int_one'                => 1,
			'int_ones'               => 1,
			'str_one'                => "1",
			'str_ones'               => "1",
			'bool_true'              => true,
			'bool_trues'             => true,
			'array_int_one'          => array(1),
			'array_int_ones'         => array(1),
			'array_str_one'          => array("1"),
			'array_str_ones'         => array("1"),
			'array_bool_true'        => array(true),
			'array_bool_trues'       => array(true),
			'array_int_one_str_two'  => array(1, "2"),
			'array_int_one_str_twos' => array(1, "2"),
			'array_str_one_int_two'  => array("1", 2),
			'array_str_one_int_twos' => array("1", 2)
		);
	}
};
