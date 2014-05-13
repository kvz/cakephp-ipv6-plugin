<?php
App::uses('Ipv6ableBehavior', 'Ipv6.Model/Behavior');

class Ipv6ableBehaviorTest extends CakeTestCase {
	public function testToRevNibblesArpa() {
		$result = Ipv6ableBehavior::toRevNibblesArpa('2001:db8::567:89ab');
		$expected = 'b.a.9.8.7.6.5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa';
		$this->assertEqual($result, $expected);

		$result = Ipv6ableBehavior::toRevNibblesArpa('2001:9a8:0:10::');
		$expected = '0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.1.0.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa';
		$this->assertEqual($result, $expected);

		$result = Ipv6ableBehavior::toRevNibblesArpa('2001:9a8:0:200::');
		$expected = '0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.2.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa';
		$this->assertEqual($result, $expected);
	}

	public function testToRevNibblesArpaWithoutPadding() {
		$result = Ipv6ableBehavior::toRevNibblesArpa('2001:9a8:0:10::', false);
		$expected = '0.1.0.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa';
		$this->assertEqual($result, $expected);

		$result = Ipv6ableBehavior::toRevNibblesArpa('2001:9a8:0:200::', false);
		$expected = '0.0.2.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa';
		$this->assertEqual($result, $expected);
	}

	public function testFromRevNibblesArpa() {
		$expected = '2001:9a8:0:10::';
		$result = Ipv6ableBehavior::fromRevNibblesArpa('0.1.0.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa');
		$this->assertEqual($result, $expected);

		$result = Ipv6ableBehavior::fromRevNibblesArpa('0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.1.0.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa');
		$this->assertEqual($result, $expected);

		$expected = '2001:9a8:0:200::';
		$result = Ipv6ableBehavior::fromRevNibblesArpa('0.0.2.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa');
		$this->assertEqual($result, $expected);

		$result = Ipv6ableBehavior::fromRevNibblesArpa('0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.2.0.0.0.0.0.8.a.9.0.1.0.0.2.ip6.arpa');
		$this->assertEqual($result, $expected);

		$result = Ipv6ableBehavior::fromRevNibblesArpa('b.a.9.8.7.6.5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa');
		$expected = '2001:db8::567:89ab';
		$this->assertEqual($result, $expected);
	}
}
