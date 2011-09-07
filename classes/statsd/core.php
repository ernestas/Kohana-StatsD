<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Sends statistics to The stats daemon over UDP
 *
 * @link http://github.com/etsy/statsd
 */
class StatsD_Core
{
	/**
	 * Log timing information
	 *
	 * @param  string   $stats        The metric to in log timing info for.
	 * @param  float    $time         The ellapsed time (ms) to log
	 * @param  float|1  $sample_rate  The rate (0-1) for sampling.
	 */
	public static function timing($stat, $time, $sample_rate = 1)
	{
		StatsD::send(array($stat => $time.'|ms'), $sample_rate);
	}

	/**
	 * Increments one or more stats counters
	 *
	 * @param   string|array  $stats        The metric(s) to increment.
	 * @param   float|1       $sample_rate  The rate (0-1) for sampling.
	 * @return  boolean
	 */
	public static function increment($stats, $sample_rate = 1)
	{
		StatsD::update_stats($stats, 1, $sample_rate);
	}

	/**
	 * Decrements one or more stats counters.
	 *
	 * @param   string|array  $stats        The metric(s) to decrement.
	 * @param   float|1       $sample_rate  The rate (0-1) for sampling.
	 * @return  boolean
	 */
	public static function decrement($stats, $sample_rate = 1)
	{
		StatsD::update_stats($stats, -1, $sample_rate);
	}

	/**
	 * Updates one or more stats counters by arbitrary amounts.
	 *
	 * @param   string|array  $stats        The metric(s) to update. Should be
	 *                                      either a string or array of metrics.
	 * @param   int|1         $delta        The amount to increment/decrement
	 *                                      each metric by.
	 * @param   float|1       $sample_rate  The rate (0-1) for sampling.
	 * @return  boolean
	 */
	public static function update_stats($stats, $delta = 1, $sample_rate = 1)
	{
		if ( ! is_array($stats))
		{
			$stats = array($stats);
		}

		$data = array();
		foreach ($stats as $stat)
		{
			$data[$stat] = $delta.'|c';
		}

		StatsD::send($data, $sample_rate);
	}

	/**
	 * Squirt The metrics over UDP
	 *
	 * @param  array  $data         Key-value array of stat name-value|type
	 * @param  int    $sample_rate  The rate (0-1) for sampling.
	 */
	public static function send($data, $sample_rate = 1)
	{
		// Sampling
		$sampled_data = array();

		if ($sample_rate < 1)
		{
			foreach ($data as $stat => $value)
			{
				if ((mt_rand() / mt_getrandmax()) <= $sample_rate)
				{
					$sampled_data[$stat] = $value.'|@'.$sample_rate;
				}
			}
		}
		else
		{
			$sampled_data = $data;
		}

		if (empty($sampled_data))
			return;

		try
		{
			$statsd = Kohana::$config->load('statsd');
			$fp = fsockopen('udp://'.$statsd['ip'], $statsd['port'], $errno, $errstr);

			if ( ! $fp)
				return;

			foreach ($sampled_data as $stat => $value)
			{
				fwrite($fp, $stat.':'.$value);
			}
			fclose($fp);
		}
		catch (Exception $e) {}
	}
}
