[As of 1.3](http://book.cakephp.org/view/1581/Miscellaneous), 
CakePHP covers IPv6 validation out of the box, and you could probably
just store those addresses as `varchars` in your database and be done with it.

But that won't let you do calculations like looking up free addresses
inside a range.

The best way to store an IPv6 would probably be to use an 128bit integer, but
MySQL does not support this, and I haven't found any
[plans of doing so](http://bugs.mysql.com/bug.php?id=3318).

[mysql-udf-ipv6](https://bitbucket.org/watchmouse/mysql-udf-ipv6/)
involves compiling, making your app less portable.

Then there are [several hacks](http://www.koopman.me/2008/04/storing-ipv6-ips-in-mysql/)
possible. This plugin transparantly implements the 'best' one.

## Usage example



## Thanks to

 - [How to convert IPv6 from binary for storage in MySQL](http://stackoverflow.com/questions/1120371/how-to-convert-ipv6-from-binary-for-storage-in-mysql)
 - [kd2.org](http://svn.kd2.org/svn/misc/libs/tools/ip_utils.php)
