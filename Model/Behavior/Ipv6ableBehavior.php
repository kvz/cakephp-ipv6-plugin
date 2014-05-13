<?php
App::uses('ModelBehavior', 'Model');

/**
 * Plugin-wrapped behavior to work with IPv6 addresses with efficient MySQL storage
 *
 * Copyright (c) 2011 Kevin van Zonneveld (http://kevin.vanzonneveld.net || kvz@php.net)
 *
 * @author Kevin van Zonneveld (kvz)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 */
class Ipv6ableBehavior extends ModelBehavior {
	public $mapMethods = array(
		'/isWithin/' => 'isWithin',
	);

	protected $_default = array(
		'error_handler' => 'php',
		'field_address' => 'address',
		'field_bits' => 'bits',
		'field_size' => 'size',
	);

	public $settings = array();


	/**
	 * Give an IPv6 in presentational format, and this will returns the range record
	 * it falls into
	 *
	 * @param object $Model
	 * @param string $denormalized
	 *
	 * @return array
	 */
	public function rangeByIp ($Model, $denormalized) {
		$ip_address = $this->normalize_ipv6($denormalized);
		$decimal    = $this->inet_ptod($ip_address);

		$res = $Model->find('all', array(
			'conditions' => array(
				$decimal . ' BETWEEN address AND (address + size - 1)'
			),
		));

		return $res;
	}

	/**
	 * This function modifies either the post data or or resultset.
	 * When $save is true, conversion is towards decimals
	 * When $save is false, conversion is towards presentational
	 *
	 * @param object  $Model
	 * @param boolean $save
	 * @param array   &$set
	 */
	protected function _changeSet ($Model, $save, &$set) {
		$field_address = $this->opt($Model, 'field_address');
		$field_bits    = $this->opt($Model, 'field_bits');
		$field_size    = $this->opt($Model, 'field_size');
		if (is_array($set) && array_key_exists($field_address, $set)) {
			if ($save) {
				$set[$field_address] = $this->normalize_ipv6($set[$field_address]);
				$set[$field_address] = $this->inet_ptod($set[$field_address]);
			} else {
				$set[$field_address] = $this->inet_dtop($set[$field_address]);
				#$set[$addressField] = $this->normalize_ipv6($set[$addressField]);
			}
		}
		if (is_array($set) && array_key_exists($field_bits, $set)) {
			if ($save) {
				$set[$field_size] = bcpow(2, 128 - $set[$field_bits]);
			}
		}
	}

	/**
	 * Calls _changeSet to modify post data
	 *
	 * @param object $Model
	 *
	 * @return boolean
	 */
	public function beforeSave (Model $Model, $options = array()) {
		$this->_changeSet($Model, true, $Model->data[$Model->alias]);
		return true;
	}

	/**
	 * Calls _changeSet to modify resultsets
	 *
	 * @param object  $Model
	 * @param array   $results
	 * @param boolean $primary
	 *
	 * @return array
	 */
	public function afterFind (Model $Model, $results, $primary = false) {
		foreach ($results as $i => $result) {
			$this->_changeSet($Model, false, $results[$i][$Model->alias]);
		}

		return $results;
	}

	/**
	 * Convert an IP address from presentation to decimal(39,0) format suitable for storage in MySQL
	 * http://stackoverflow.com/questions/1120371/how-to-convert-ipv6-from-binary-for-storage-in-mysql/1271123#1271123
	 *
	 * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
	 *
	 * @return string The IP address in decimal notation
	 */
	public function inet_ptod ($ip_address) {
		// IPv4 address
		if (strpos($ip_address, ':') === false && strpos($ip_address, '.') !== false) {
			$ip_address = '::' . $ip_address;
		}

		// IPv6 address
		if (strpos($ip_address, ':') !== false) {
			$network = inet_pton($ip_address);
			$parts   = unpack('N*', $network);

			foreach ($parts as &$part) {
				if ($part < 0) {
					$part = bcadd((string) $part, '4294967296');
				}

				if (!is_string($part)) {
					$part = (string) $part;
				}
			}

			$decimal = $parts[4];
			$decimal = bcadd($decimal, bcmul($parts[3], '4294967296'));
			$decimal = bcadd($decimal, bcmul($parts[2], '18446744073709551616'));
			$decimal = bcadd($decimal, bcmul($parts[1], '79228162514264337593543950336'));

			return $decimal;
		}

		// Decimal address
		return $ip_address;
	}

	/**
	 * Convert an IP address from decimal format to presentation format
	 * http://stackoverflow.com/questions/1120371/how-to-convert-ipv6-from-binary-for-storage-in-mysql/1271123#1271123
	 *
	 * @param string $decimal An IP address in IPv4, IPv6 or decimal notation
	 *
	 * @return string The IP address in presentation format
	 */
	public function inet_dtop ($decimal) {
		// IPv4 or IPv6 format
		if (strpos($decimal, ':') !== false || strpos($decimal, '.') !== false) {
			return $decimal;
		}

		// Decimal format
		$parts = array();
		$parts[1] = bcdiv($decimal, '79228162514264337593543950336', 0);
		$decimal  = bcsub($decimal, bcmul($parts[1], '79228162514264337593543950336'));
		$parts[2] = bcdiv($decimal, '18446744073709551616', 0);
		$decimal  = bcsub($decimal, bcmul($parts[2], '18446744073709551616'));
		$parts[3] = bcdiv($decimal, '4294967296', 0);
		$decimal  = bcsub($decimal, bcmul($parts[3], '4294967296'));
		$parts[4] = $decimal;

		foreach ($parts as &$part) {
			if (bccomp($part, '2147483647') == 1) {
				$part = bcsub($part, '4294967296');
			}

			$part = (int) $part;
		}

		$network    = pack('N4', $parts[1], $parts[2], $parts[3], $parts[4]);
		$ip_address = inet_ntop($network);

		// Turn IPv6 to IPv4 if it's IPv4
		if (preg_match('/^::\d+.\d+.\d+.\d+$/', $ip_address)) {
			return substr($ip_address, 2);
		}

		return $ip_address;
	}

	/**
	 * Takes an ipv6 address and converts it to DNS reverse nibble arpa format
	 *
	 * Taken from http://stackoverflow.com/a/6621473/151666
	 *
	 * @param type $ip
	 *
	 * @return string
	 */
	public static function toRevNibblesArpa ($ip, $zeropad = true) {
		$addr   = inet_pton($ip);
		$unpack = unpack('H*hex', $addr);
		$hex    = $unpack['hex'];
		$arpa   = join('.', array_reverse(str_split($hex))) . '.ip6.arpa';

		if (!$zeropad) {
			// Group into 4 bits
			$padding = '0.0.0.0.';
			while (strpos($arpa, $padding) === 0) {
				$arpa = substr($arpa, strlen($padding));
			}
		}

		return $arpa;
	}

	/**
	 * Takes DNS reverse nibble arpa format and converts it to an ipv6 address
	 *
	 * @param type $arpa
	 *
	 * @return string
	 */
	public static function fromRevNibblesArpa ($arpa) {
		$mainPTR = substr($arpa, 0, -9);

		$reverse = strrev($mainPTR);
		$reverse = str_replace('.', '', $reverse);

		$octets  = str_split($reverse, 4);
		$missing = 8 - (count($octets));
		for ($i = 0; $i < $missing; $i++) {
			$octets[] = '0000';
		}

		$address = implode(':', $octets);
		return inet_ntop(inet_pton($address));
	}

	/**
	 * Normalizes an IPv6 address to long notation.
	 *
	 * Examples:
	 *  -- ::1
	 *  -> 0000:0000:0000:0000:0000:0000:0000:0001
	 *  -- 2001:db8:85a3::8a2e:370:7334
	 *  -> 2001:0db8:85a3:0000:0000:8a2e:0370:7334
	 *
	 * http://svn.kd2.org/svn/misc/libs/tools/ip_utils.php
	 *
	 * @param string $ip Input IPv6 address
	 *
	 * @return string IPv6 address
	 */
	public function normalize_ipv6 ($ip, $zeropad = true) {
		if (!is_string($ip)) {
			return $ip;
		}
		if (false !== strpos($ip, '::')) {
			$ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
		}

		if ($ip[0] == ':') {
			$ip = '0' . $ip;
		}

		$ip = explode(':', $ip);

		if ($zeropad) {
			foreach ($ip as &$part) {
				$part = str_pad($part, 4, '0', STR_PAD_LEFT);
			}
		}

		return join(':', $ip);
	}

	/**
	 * Merges settings over default ones
	 *
	 * @param object $Model
	 * @param array  $settings
	 */
	public function setup (Model $Model, $settings = array()) {
		$this->settings[$Model->alias] = Set::merge(
			$this->_default,
			$settings
		);
	}

	/**
	 * Error handler
	 *
	 * @param <type> $Model
	 * @param <type> $format
	 * @param <type> $arg1
	 * @param <type> $arg2
	 * @param <type> $arg3
	 *
	 * @return boolean
	 */
	public function err ($Model, $format, $arg1 = null, $arg2 = null, $arg3 = null) {
		$arguments = func_get_args();
		$Model     = array_shift($arguments);
		$format    = array_shift($arguments);

		$str = $format;
		if (count($arguments)) {
			foreach($arguments as $k => $v) {
				$arguments[$k] = $this->sensible($v);
			}
			$str = vsprintf($str, $arguments);
		}

		$this->error = $str;
		#$Model->onError();

		if (@$this->settings[$Model->alias]['error_handler'] === 'php') {
			trigger_error($str, E_USER_ERROR);
		}

		return false;
	}

	/**
	 * Takes any variable and makes it human readable
	 *
	 * @param string $arguments
	 *
	 * @return string
	 */
	public function sensible ($arguments) {
		if (is_object($arguments)) {
			return get_class($arguments);
		}
		if (!is_array($arguments)) {
			if (!is_numeric($arguments) && !is_bool($arguments)) {
				$arguments = "'" . $arguments . "'";
			}
			return $arguments;
		}
		$arr = array();
		foreach ($arguments as $key => $val) {
			if (is_array($val)) {
				$val = json_encode($val);
			} elseif (is_object($val)) {
				$val = get_class($val);
			} elseif (!is_numeric($val) && !is_bool($val)) {
				$val = "'" . $val . "'";
			}

			if (strlen($val) > 33) {
				$val = substr($val, 0, 30) . '...';
			}

			$arr[] = $key . ': ' . $val;
		}

		return join(', ', $arr);
	}

	/**
	 * Used to get and change settings
	 *
	 * @return mixed
	 */
	public function opt () {
		$args  = func_get_args();

		// Strip model from args if needed
		if (is_object($args[0])) {
			$Model = array_shift($args);
		} else {
			return $this->err('First argument needs to be a model');
		}

		// Strip method from args if needed (e.g. when called via $Model->mappedMethod())
		if (is_string($args[0])) {
			foreach ($this->mapMethods as $pattern => $meth) {
				if (preg_match($pattern, $args[0])) {
					$method = array_shift($args);
					break;
				}
			}
		}

		$count = count($args);
		if ($count > 1) {
			$this->settings[$Model->alias][$args[0]] = $args[1];
		} else if ($count > 0) {
			if (!array_key_exists($args[0], $this->settings[$Model->alias])) {
				return $this->err(
					$Model,
					'Option %s was not set',
					$args[0]
				);
			}
			return $this->settings[$Model->alias][$args[0]];
		} else {
			return $this->err(
				$Model,
				'Found remaining arguments: %s Opt needs more arguments (1 for Model; 1 more for getting, 2 more for setting)',
				$args
			);
		}
	}
}
