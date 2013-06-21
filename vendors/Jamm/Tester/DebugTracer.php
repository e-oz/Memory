<?php
namespace Jamm\Tester;

class DebugTracer
{
	protected $trace_max_depth = 7;
	protected $trace_start_depth = 4;
	protected $trace_space_separator = '|';
	protected $trace_new_line_separator = "\n";
	protected $max_arr_dump_lines = 5;

	public function setTraceSpaceSeparator($trace_space_separator = '|')
	{
		$this->trace_space_separator = $trace_space_separator;
	}

	public function setTraceStartDepth($trace_start_depth = 4)
	{
		$this->trace_start_depth = $trace_start_depth;
	}

	public function setTraceMaxDepth($trace_max_depth = 4)
	{
		$this->trace_max_depth = $trace_max_depth;
	}

	public function getCurrentBacktrace()
	{
		$tmp = array_slice(debug_backtrace(), $this->trace_start_depth);
		if (empty($tmp)) return false;
		$str               = '';
		$space             = $basespace = $this->trace_space_separator;
		$depth             = 0;
		$ignored_functions = array(__METHOD__, 'trigger_error', 'include_once', 'include', 'require', 'require_once');
		foreach ($tmp as $t)
		{
			if (!isset($t['file'])) $t['file'] = '[not a file]';
			if (!isset($t['line'])) $t['line'] = '[-1]';
			if (in_array($t['function'], $ignored_functions)) continue;
			$str .= ' '.$space.$t['file']."\t[".$t['line']."]\t";
			if (array_key_exists('class', $t))
			{
				$str .= $t['class'];
				if (isset($t['type'])) $str .= $t['type'];
			}
			$str .= $t['function'];
			if (isset($t['args'][0]))
			{
				$args = array();
				$str .= '(';
				foreach ($t['args'] as $t_arg)
				{
					if (!is_scalar($t_arg))
					{
						if (is_array($t_arg))
						{
							$args[] = $this->getNonRecursiveDumpOfArray($t_arg);
						}
						elseif (is_object($t_arg))
						{
							$args[] = get_class($t_arg);
						}
						else
						{
							$args[] = '[not scalar]';
						}
					}
					else
					{
						if (strlen($t_arg) > 128) $args[] = '['.substr($t_arg, 0, 128).'...]';
						else $args[] = $t_arg;
					}
				}
				$str .= implode(', ', $args).')';
			}
			else  $str .= '()';
			$str .= $this->trace_new_line_separator;
			$space .= $basespace;
			$depth++;
			if ($depth >= $this->trace_max_depth) break;
		}
		return rtrim($str);
	}

	protected function getNonRecursiveDumpOfArray($array)
	{
		$dump = 'array(';
		$i    = 0;
		if (!empty($array))
		{
			foreach ($array as $key => $value)
			{
				$dump .= $this->trace_new_line_separator;
				$i++;
				if ($i > $this->max_arr_dump_lines)
				{
					$dump .= '...';
					break;
				}
				if (is_object($value))
				{
					$value = 'Object '.get_class($value);
				}
				elseif (is_array($value))
				{
					$value = print_r($value, 1);
				}
				$dump .= '['.$key.'] => ['.strval($value).']';
			}
		}
		$dump .= ')';
		return $dump;
	}

	/**
	 * @param int $max_arr_dump_lines maximum elements of array in the backtrace dump
	 */
	public function setMaxArrDumpLines($max_arr_dump_lines)
	{
		$this->max_arr_dump_lines = intval($max_arr_dump_lines);
	}

	/**
	 * @param string $trace_new_line_separator
	 */
	public function setTraceNewLineSeparator($trace_new_line_separator = "\n")
	{
		$this->trace_new_line_separator = $trace_new_line_separator;
	}
}
