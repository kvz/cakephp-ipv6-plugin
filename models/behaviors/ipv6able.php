<?php
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

    public $settings = array();
    
    protected $_default = array(
        'error_handler' => 'php',
        'field_address' => 'address',
        'field_bits' => 'bits',
        'field_size' => 'size',
    );

    public function setup ($Model, $settings = array()) {
        $this->settings[$Model->alias] = array_merge(
            $this->_default,
            $settings
        );
        
        $this->rangeByIp($Model, '2001:990:0:1522::2');
    }

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
        $Model->onError();

        if ($this->config['error_handler'] === 'php') {
            trigger_error($str, E_USER_ERROR);
        }

        return false;
    }

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

    public function opt ($Model) {
        $args  = func_get_args();
        $count = count($args);
        
        if ($count > 2) {
            $this->settings[$Model->alias][$args[1]] = $args[2];
        } else if ($count > 1) {
            if (!array_key_exists($args[1], $this->settings[$Model->alias])) {
                return $this->err(
                    $Model,
                    'Option %s was not set',
                    $args[1]
                );
            }
            return $this->settings[$Model->alias][$args[1]];
        } else {
            return $this->err(
                $Model,
                'Opt needs more arguments (1 for Model; 1 more for getting, 2 more for setting)'
            );
        }
    }

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

    public function beforeSave ($Model) {
        $this->_changeSet($Model, true, $Model->data[$Model->alias]);
        return true;
    }
    
    public function afterFind ($Model, $results, $primary) {
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
    public function normalize_ipv6 ($ip) {
        if (false !== strpos($ip, '::')) {
            $ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
        }

        if ($ip[0] == ':') {
            $ip = '0' . $ip;
        }

        $ip = explode(':', $ip);

        foreach ($ip as &$part) {
            $part = str_pad($part, 4, '0', STR_PAD_LEFT);
        }

        return join(':', $ip);
    }
}