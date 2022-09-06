<?php

class	ch {
	var	$ch = 0;
	var	$l = 480;		# 1/4
	var	$o = 4;
	var	$q = 7;
	var	$v = 127;
	var	$head = 0;
	function	__construct($ch) {
		$this->ch = $ch;
	}
	function	k4note($s = "C") {
		$v = $this->o * 12 + 12 + strpos("c d ef g a b", strtolower(substr($s, 0, 1)));
		switch (substr($s, 1)) {
			case	"-":
				return $v - 1;
			case	"+":
			case	"#":
				return $v + 1;
		}
		return $v;
	}
}
$chlist = array();
for ($i=0; $i<16; $i++)
	$chlist[$i] = new ch($i);
$ch = $chlist[0];

$eventlist = array();

function	put($pos, $a)
{
	global	$eventlist;
	
	$s = "";
	foreach ($a as $v)
		$s .= chr($v);
	@$eventlist[$pos][] = $s;
}

if (!function_exists("hex2bin")) {
	function	hex2bin($s)
	{
		$ret = "";;
		$s = strtolower($s);
		$hex2bin = "0123456789abcdef";
		$i = 0;
		while ($i < strlen($s)) {
			$v = strpos($hex2bin, substr($s, $i++, 1)) << 4;
			$v |= strpos($hex2bin, substr($s, $i++, 1));
			$ret .= chr($v);
		}
		return $ret;
	}
}


$pos = $head = $tail = 0;
while (($s = fgets(STDIN)) !== FALSE) {
	if (preg_match('/^#/', trim($s)))
		continue;
	while (($s = trim($s)) != "") {
		if (!preg_match('/^([ A-Za-z<>])([-+#])?(.*)/', $s, $a)) {
			fprintf(STDERR, "unknown command: %s\n", $s);
			die(1);
		}
		$s = $a[3];
		switch ($c = strtolower($a[1])) {
			case	"<":
				$ch->o--;
				continue 2;
			case	">":
				$ch->o++;
				continue 2;
			case	"x":
				preg_match('/^([0-9A-Fa-f]*)(.*)/', $s, $a);
				$s = $a[2];
				$s0 = hex2bin($a[1]);
				if (ord(substr($s0, 0, 1)) == 0xf0) {
					$len = strlen($s0) - 1;
					$s1 = chr(0xf0);
					if ($len >= 0x4000)
						$s1 .= chr(0x80 | (($len >> 14) & 0x7f));
					if ($len >= 0x80)
						$s1 .= chr(0x80 | (($len >> 7) & 0x7f));
					$s1 .= chr($len & 0x7f);
					@$eventlist[$pos][] = $s1.substr($s0, 1);
				} else {
					$len = strlen($s0);
					$s1 = chr(0xf7);
					if ($len >= 0x4000)
						$s1 .= chr(0x80 | (($len >> 14) & 0x7f));
					if ($len >= 0x80)
						$s1 .= chr(0x80 | (($len >> 7) & 0x7f));
					$s1 .= chr($len & 0x7f);
					@$eventlist[$pos][] = $s1.$s0;
				}
			case	" ":
				continue 2;
		}
		$l = $v = 0;
		do {
			preg_match('/^([0-9]*)([.])?(&)?(.*)/', $s, $a2);
			$v = $a2[1] + 0;
			$s = $a2[4];
			if (($a2[1] == "")||($a2[1] <= 0)) {
				$l += $ch->l;
				if ($a2[2] != "")
					$l += $ch->l / 2;
			} else if ($a2[2] == "")
				$l += 1920 / $a2[1];
			else
				$l += 2880 / $a2[1];
		} while ($a2[3] != "");
		switch ($c = strtolower($a[1])) {
			default:
				fprintf(STDERR, "unknown command: [%s] %s\n", $c, $s);
				die(1);
			case	"a":
			case	"b":
			case	"c":
			case	"d":
			case	"e":
			case	"f":
			case	"g":
				$k = $ch->k4note($c.$a[2]);
				put($pos, array(0x90 + $ch->ch, $k, $ch->v));
				put($pos + $l * $ch->q / 8, array(0x90 + $ch->ch, $k, 0));
			case	"r":
				$pos += $l;
				break;
			case	"p":
				put($pos, array(0xc0 + $ch->ch, $v));
				break;
			case	"n":
				put($pos, array(0xb0 + $ch->ch, ($v / 1000) & 0x7f, ($v % 1000) & 0x7f));
				break;
			case	"l":
				$ch->$c = $l;
				break;
			case	"o":
			case	"q":
			case	"v":
				$ch->$c = $v;
				break;
			case	"h":
				if ($tail < $pos)
					$tail = $pos;
#				if ($v <= $ch->ch)
#					$head = $pos = $tail;
#				else
#					$pos = $head;
				$ch->head = $pos;
				if (($ch = @$chlist[$v]) === null) {
					fprintf(STDERR, "ch(%s) %s\n", $v, $s);
					die(1);
				}
				if ($ch->head <= $head)
					$pos = $head;
				else
					$pos = $head = $tail;
				break;
			case	"t":
				if ($v <= 0)
					break;
				$v = 60000000 / $v;
				put($pos, array(0xff, 0x51, 3, ($v >> 16) & 0xff, ($v >> 8) & 0xff, $v & 0xff));
				break;
		}
	}
}

ksort($eventlist);
if (0) {
	foreach ($eventlist as $t => $list) {
		printf("%8d :\n", $t);
		foreach ($list as $v)
			printf("\t%s\n", bin2hex($v));
	}
	die();
}

$out = "";
$torg = 0;
foreach ($eventlist as $t0 => $list) {
	$s = "";
	$t = $t0 - $torg;
	$torg = $t0;
	foreach ($list as $v) {
		if ($t >= 0x10000000)
			$s .= chr(0x80 | (($t >> 28) & 0x7f));
		if ($t >= 0x200000)
			$s .= chr(0x80 | (($t >> 21) & 0x7f));
		if ($t >= 0x4000)
			$s .= chr(0x80 | (($t >> 14) & 0x7f));
		if ($t >= 0x80)
			$s .= chr(0x80 | (($t >> 7) & 0x7f));
		$s .= chr($t & 0x7f);
		$s .= $v;
		$t = 0;
	}
	$out .= $s;
}
$out .= hex2bin("ff2f00");

print hex2bin("4d546864000000060000000101e0");
$l = strlen($out);
print hex2bin("4d54726b").chr(($l >> 24) & 0xff).chr(($l >> 16) & 0xff).chr(($l >> 8) & 0xff).chr($l & 0xff);
print $out;
