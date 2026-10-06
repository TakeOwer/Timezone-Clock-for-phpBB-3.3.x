<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @copyright (c) 2020 HiFiKabin & ctrstudio (original "Timezone Clock")
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\core;

/**
 * Pure (no database) engine for the IANA time zone database in the
 * "packed" format published by moment-timezone (data/packed/latest.json).
 *
 * Every transition is kept as an absolute UTC instant, so the clocks never
 * depend on the tz database of the server or of the visitor's browser:
 * when a country changes its rules it is enough to refresh this data.
 *
 * A transition entry is: [until_ms|null, offset_east_minutes, abbr, is_dst]
 * meaning "this offset is in force until 'until' (exclusive)".
 */
class tzdata
{
	/** Longest period that can still be considered "daylight saving" (days) */
	const MAX_DST_DAYS = 270;

	/**
	 * Decode a moment-timezone base-60 number
	 */
	public static function unpack_base60($string)
	{
		$string = (string) $string;
		$sign = 1;
		$i = 0;

		if ($string !== '' && $string[0] === '-')
		{
			$sign = -1;
			$i = 1;
		}

		$parts = explode('.', $string);
		$whole = $parts[0];
		$fractional = isset($parts[1]) ? $parts[1] : '';
		$out = 0.0;

		for ($len = strlen($whole); $i < $len; $i++)
		{
			$out = 60 * $out + self::char_to_int(ord($whole[$i]));
		}

		$multiplier = 1.0;
		for ($j = 0, $len = strlen($fractional); $j < $len; $j++)
		{
			$multiplier = $multiplier / 60;
			$out += self::char_to_int(ord($fractional[$j])) * $multiplier;
		}

		return $out * $sign;
	}

	protected static function char_to_int($code)
	{
		if ($code > 96)
		{
			return $code - 87;
		}
		if ($code > 64)
		{
			return $code - 29;
		}
		return $code - 48;
	}

	/**
	 * Unpack a single packed zone string
	 *
	 * @return array|false [name, abbrs[], offsets[] (minutes WEST), untils[] (ms, last = null), population]
	 */
	public static function unpack_zone($packed)
	{
		$data = explode('|', (string) $packed);
		if (count($data) < 5 || $data[0] === '')
		{
			return false;
		}

		$abbr_src = explode(' ', $data[1]);
		$offset_src = array_map([__CLASS__, 'unpack_base60'], explode(' ', $data[2]));
		$indices = array_map([__CLASS__, 'unpack_base60'], str_split($data[3]));
		$untils_raw = ($data[4] === '') ? [] : array_map([__CLASS__, 'unpack_base60'], explode(' ', $data[4]));

		$count = count($indices);
		$untils = [];
		$prev = 0;
		for ($i = 0; $i < $count; $i++)
		{
			$delta = isset($untils_raw[$i]) ? $untils_raw[$i] : 0;
			$prev = (float) round($prev + $delta * 60000);
			$untils[$i] = $prev;
		}
		if ($count)
		{
			$untils[$count - 1] = null;
		}

		$abbrs = $offsets = [];
		foreach ($indices as $i => $idx)
		{
			$idx = (int) $idx;
			$abbrs[$i] = isset($abbr_src[$idx]) ? $abbr_src[$idx] : '';
			$offsets[$i] = isset($offset_src[$idx]) ? $offset_src[$idx] : 0;
		}

		return [
			'name'			=> $data[0],
			'abbrs'			=> $abbrs,
			'offsets'		=> $offsets,
			'untils'		=> $untils,
			'population'	=> isset($data[5]) ? (int) self::parse_population($data[5]) : 0,
		];
	}

	protected static function parse_population($value)
	{
		// population is written like "48e5"
		return is_numeric($value) ? (float) $value : 0;
	}

	/**
	 * Build the transition list for an unpacked zone, dropping everything
	 * that ended before $from_ms. DST is detected on the full history:
	 * a period is DST when its offset is ahead of both neighbours and it
	 * lasts less than MAX_DST_DAYS (this excludes permanent changes and
	 * "Ramadan style" inversions such as Morocco).
	 *
	 * @return array list of [until|null, offset_east, abbr, dst]
	 */
	public static function build_transitions(array $zone, $from_ms, $to_ms = null)
	{
		$n = count($zone['offsets']);
		$out = [];

		for ($i = 0; $i < $n; $i++)
		{
			$until = $zone['untils'][$i];
			if ($until !== null && $until <= $from_ms)
			{
				continue;
			}

			$east = (int) round(-$zone['offsets'][$i]);
			$dst = false;

			if ($i > 0 && $i < $n - 1 && $until !== null)
			{
				$prev_east = (int) round(-$zone['offsets'][$i - 1]);
				$next_east = (int) round(-$zone['offsets'][$i + 1]);
				$start = $zone['untils'][$i - 1];
				$length_days = ($start !== null) ? ($until - $start) / 86400000 : 9999;
				$dst = ($east > $prev_east && $east > $next_east && $length_days < self::MAX_DST_DAYS);
			}

			$out[] = [$until, $east, (string) $zone['abbrs'][$i], $dst];

			if ($to_ms !== null && $until !== null && $until > $to_ms)
			{
				break;
			}
		}

		return $out;
	}

	/**
	 * Entry in force at the given instant
	 */
	public static function entry_at(array $transitions, $ms)
	{
		foreach ($transitions as $t)
		{
			if ($t[0] === null || $ms < $t[0])
			{
				return $t;
			}
		}

		return $transitions ? end($transitions) : [null, 0, 'UTC', false];
	}

	/**
	 * Next change after the given instant (or null)
	 *
	 * @return array|null [at_ms, entry_after]
	 */
	public static function next_change(array $transitions, $ms)
	{
		$count = count($transitions);
		for ($i = 0; $i < $count; $i++)
		{
			$t = $transitions[$i];
			if ($t[0] === null)
			{
				return null;
			}
			if ($ms < $t[0])
			{
				return isset($transitions[$i + 1]) ? [$t[0], $transitions[$i + 1]] : null;
			}
		}

		return null;
	}

	/**
	 * Keep only what is useful from $from_ms onwards
	 */
	public static function trim(array $transitions, $from_ms)
	{
		$out = [];
		foreach ($transitions as $t)
		{
			if ($t[0] === null || $t[0] > $from_ms)
			{
				$out[] = $t;
			}
		}

		return $out;
	}

	/**
	 * Drop everything after the entry that covers $to_ms
	 * (the data published by moment-timezone goes on until year 2500)
	 */
	public static function cap(array $transitions, $to_ms)
	{
		$out = [];
		foreach ($transitions as $t)
		{
			$out[] = $t;
			if ($t[0] === null || $t[0] > $to_ms)
			{
				break;
			}
		}

		return $out;
	}

	/**
	 * Fingerprint of the rules in force in the next 5 years. Used to
	 * detect when an update really changed the rules of a zone.
	 */
	public static function fingerprint(array $transitions, $from_ms)
	{
		$parts = [];
		foreach (self::cap(self::trim($transitions, $from_ms), $from_ms + 5 * 31557600000) as $t)
		{
			$parts[] = ($t[0] === null ? 'inf' : sprintf('%.0f', $t[0])) . ':' . $t[1];
		}

		return md5(implode(',', $parts));
	}

	/**
	 * Format an offset in minutes east as "+05:30"
	 */
	public static function format_offset($minutes)
	{
		$minutes = (int) $minutes;
		$sign = $minutes < 0 ? '-' : '+';
		$minutes = abs($minutes);

		return $sign . sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
	}

	/**
	 * Current time in milliseconds
	 */
	public static function now_ms()
	{
		return (float) floor(microtime(true) * 1000);
	}

	/**
	 * Human city name from a zone identifier: "America/Sao_Paulo" -> "Sao Paulo"
	 */
	public static function zone_city($zone)
	{
		$pos = strrpos($zone, '/');
		$city = ($pos === false) ? $zone : substr($zone, $pos + 1);

		return str_replace('_', ' ', $city);
	}
}
