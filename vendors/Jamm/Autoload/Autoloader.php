<?php
namespace Jamm\Autoload;
/**
 * Class to organize autoloading by PHP naming conventions
 *
 * A fully-qualified namespace and class must have the following structure \<Vendor Name>\(<Namespace>\)*<Class Name>
 * Each namespace must have a top-level namespace ("Vendor Name").
 * Each namespace can have as many sub-namespaces as it wishes.
 * Each namespace separator is converted to DIRECTORY_SEPARATOR when loading from the file system.
 * Each "_" character in the CLASS NAME is converted to DIRECTORY_SEPARATOR. The "_" character has no special meaning in the namespace.
 * The fully-qualified namespace and class is suffixed with ".php" when loading from the file system.
 * Alphabetic characters in vendor names, namespaces, and class names may be of any combination of lower case and upper case.
 *
 * Default place of "vendors" folder is __DIR__.'/../../' directory,
 * you can change it: use set_modules_dir() method
 *
 * Classes of all packages (libraries), placed in the "vendors" folder, will be autoloaded automatically (in first use).
 * You can map namespace of package, placed in any folder: use register_namespace_dir() method.
 * You can map any class also: use register_class() method.
 *
 * In case of errors E_USER_WARNING will be triggered
 *
 * Methods of this class doesn't throws exceptions
 *
 * @author  OZ <normandiggs@gmail.com>
 * @license http://en.wikipedia.org/wiki/MIT_License MIT
 */
class Autoloader
{
	protected $classes = array();
	protected $modules_dir;
	protected $functions = array();
	protected $namespaces_dirs = array();
	protected $warn_about_not_found = false;
	protected $search_log;

	/**
	 * @param bool $autostart - call method start in constructor
	 */
	public function __construct($autostart = true)
	{
		if ($autostart) $this->start();
	}

	/**
	 * start autoloader (register in spl_autoload)
	 */
	public function start()
	{
		$this->define_home_dir_constant();
		$this->register_root_namespace();
		spl_autoload_register(array($this, 'autoload'));
	}

	/**
	 * Define HOME_DIR - by this constant other modules can check, if autoloader is exists
	 */
	protected function define_home_dir_constant()
	{
		if (!defined('HOME_DIR'))
		{
			$home = explode(DIRECTORY_SEPARATOR, __DIR__);
			$home = DIRECTORY_SEPARATOR.$home[1].DIRECTORY_SEPARATOR.$home[2];
			define('HOME_DIR', $home, true);
		}
	}

	/**
	 * Register "vendors" directory as root namespace
	 * @return void
	 */
	private function register_root_namespace()
	{
		$this->register_namespace_dir('', $this->get_modules_dir());
	}

	/**
	 * Associate namespace with directory
	 * For example, if namespace '\name\space' will be associated with directory '/home/name/space',
	 * class '\name\space\subnamespace\Class_Name.php' will be looked in /home/name/space/subnamespace/Class_Name.php
	 * @param string $namespace name\space\ (last symbol - slash, and no slashes in start)
	 * @param string $dir
	 */
	public function register_namespace_dir($namespace, $dir)
	{
		if (($dir = realpath($dir))===false)
		{
			trigger_error('Namespace was not registered! Directory not found', E_USER_WARNING);
			return false;
		}
		if (!empty($namespace)) $namespace = trim($namespace, '\\').'\\';
		$this->namespaces_dirs[$namespace] = $dir.DIRECTORY_SEPARATOR;
	}

	/**
	 * Register class - associate name of the class with path to the file (mapping)
	 * @param string $class_name
	 * @param string $path
	 * @return bool
	 */
	public function register_class($class_name, $path)
	{
		$class_name = strtolower($class_name);
		if ($path[0]!=DIRECTORY_SEPARATOR) $path = $this->get_modules_dir().DIRECTORY_SEPARATOR.$path;
		$this->classes[$class_name] = $path;
	}

	/**
	 * Return modules dir like "/home/dir/modules"
	 * By default will be taken directory of this file without two last folders (__DIR__.'/../../')
	 * @return string
	 */
	public function get_modules_dir()
	{
		if (empty($this->modules_dir)) $this->set_modules_dir(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
		return $this->modules_dir;
	}

	/**
	 * Set directory of modules ("vendors")
	 * @param string $dir
	 * @return void
	 */
	public function set_modules_dir($dir)
	{
		$dir = realpath($dir);
		if (!empty($dir) && is_dir($dir)) $this->modules_dir = $dir;
		else
		{
			trigger_error('Autoloader can not set modules directory: '.$dir);
		}
	}

	public function set_warn_about_not_found($warn_about_not_found = true)
	{
		$this->warn_about_not_found = $warn_about_not_found;
	}

	public function get_search_log()
	{
		return $this->search_log;
	}

	public function get_namespaces()
	{
		return $this->namespaces_dirs;
	}

	/**
	 * @param string $class_name
	 * @return bool
	 */
	public function autoload($class_name)
	{
		if ($class_name[0]=='\\') $class_name = substr($class_name, 1);
		$this->search_log = NULL;
		$file             = $this->find_in_classes($class_name);
		if (empty($file)) $file = $this->find_in_namespaces($class_name);
		if (!empty($file))
		{
			return $this->include_class_file($class_name, $file);
		}
		else
		{
			$this->warning_class_not_found($class_name);
			return false;
		}
	}

	protected function include_class_file($class_name, $file)
	{
		/** @noinspection PhpIncludeInspection */
		include $file;
		if (!class_exists($class_name, false) && !interface_exists($class_name, false))
		{
			if (strpos(PHP_VERSION, '5.4')!==false)
			{
				if (trait_exists($class_name, false))
				{
					return true;
				}
			}
			trigger_error('Class '.$class_name.' was not declared in included file: '.$file, E_USER_WARNING);
			return false;
		}
		return true;
	}

	private function warning_class_not_found($class_name)
	{
		if ($this->warn_about_not_found)
		{
			trigger_error('Class '.$class_name." was not found. Search log:\n ".print_r($this->search_log, 1), E_USER_WARNING);
		}
	}

	/**
	 * Find classname in registered classes
	 * @param string $class_name
	 * @return bool
	 */
	private function find_in_classes($class_name)
	{
		if (empty($this->classes)) return false;
		$class_name = strtolower($class_name);
		if (!empty($this->classes[$class_name])) return $this->classes[$class_name];
		$this->log_search_variant(__FUNCTION__, $class_name);
		return false;
	}

	/**
	 * Add next variant of filepath from searching method to the log
	 * @param string $method_title
	 * @param string $class_name
	 * @param string $filepath
	 */
	protected function log_search_variant($method_title, $class_name, $filepath = '')
	{
		$str = $method_title.': '.$class_name;
		if (!empty($filepath)) $str .= ' = > '.$filepath;
		$this->search_log[] = $str;
	}

	/**
	 * Find path to the file of class in registered namespaces (root namespace is registered automatically)
	 * @param string $class
	 * @return bool|string
	 */
	private function find_in_namespaces($class)
	{
		if (empty($this->namespaces_dirs)) return false;
		$pos = strrpos($class, '\\');
		if ($pos!==false)
		{
			$namespace  = substr($class, 0, $pos+1);
			$class_name = str_replace('_', DIRECTORY_SEPARATOR, substr($class, $pos+1));
		}
		else
		{
			$class_name = str_replace('_', DIRECTORY_SEPARATOR, $class);
			$pos        = strrpos($class_name, DIRECTORY_SEPARATOR);
			$namespace  = str_replace(DIRECTORY_SEPARATOR, '\\', substr($class_name, 0, $pos+1));
			$class_name = substr($class_name, $pos+1);
		}
		if (count($this->namespaces_dirs) > 1)
		{
			foreach ($this->namespaces_dirs as $ns => $dir)
			{
				if (stripos($namespace, $ns)===0)
				{
					$filepath = $this->lookFileInNamespaceDir($namespace, $ns, $class_name, $dir);
					if (!empty($filepath)) return $filepath;
				}
			}
		}
		$dir      = $this->namespaces_dirs[''];
		$filepath = $this->lookFileInNamespaceDir($namespace, '', $class_name, $dir);
		if (!empty($filepath)) return $filepath;
		return false;
	}

	private function lookFileInNamespaceDir($class_namespace, $mapped_namespace, $class_name, $mapped_dir)
	{
		$class_path = str_replace('\\', DIRECTORY_SEPARATOR, substr($class_namespace, strlen($mapped_namespace))).$class_name;
		$filepath   = $this->generate_filepath($mapped_dir, $class_path);
		if (!empty($filepath)) return $filepath;
		$this->log_search_variant(__FUNCTION__, $class_name, $mapped_dir.$class_path.'.*');
		return false;
	}

	protected function generate_filepath($dir, $class_path)
	{
		$file = $dir.$class_path.'.php';
		if (file_exists($file)) return $file;
		$file = $dir.$class_path.'.inc';
		if (file_exists($file)) return $file;
		$file = $dir.$class_path.'.class';
		if (file_exists($file)) return $file;
		return false;
	}

}
