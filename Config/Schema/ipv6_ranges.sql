-- DROP TABLE IF EXISTS `ipv6_ranges`;
CREATE TABLE `ipv6_ranges` (
    `id` INT UNSIGNED NOT NULL auto_increment,
    `customer_id` INT UNSIGNED NOT NULL,
    `address` DECIMAL( 39, 0 ) UNSIGNED NOT NULL,
    `bits` TINYINT UNSIGNED NOT NULL DEFAULT '128',
    `size` DECIMAL( 39, 0 ) UNSIGNED NOT NULL DEFAULT '0',
    `created` DATETIME NOT NULL,
    `modified` DATETIME NOT NULL,
    PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
