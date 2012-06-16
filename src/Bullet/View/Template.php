<?php
namespace Bullet\View;
use Bullet\Response;

/**
 * View template class that will display and handle view templates
 *
 * @package Bullet
 * @author Vance Lucas <vance@vancelucas.com>
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Template extends Response
{
    // Static config setup for usage
    protected static $_config = array(
        'default_format' => 'html',
        'default_extension' => 'php',
        'path' => null
    );

    // Template specific stuff
    protected $_file;
    protected $_fileFormat;
    protected $_vars = array();
    protected $_path;
    protected $_layout;
    protected $_exists;

    // Content blocks
    protected static $_blocks = array();


    /**
     * Constructor function
     *
     * @param $file string	Template filename to use
     * @param $module string	Module template file resides in
     */
    public function __construct($file, $format = 'html', $path = null)
    {
        $this->file($file, $format);
        $this->path($path);

        $this->init();
    }


    /**
     * Config setup for main templates directory, etc.
     */
    public static function config($cfg = null)
    {
        // Getter
        if(null === $cfg) {
            return self::$_config;
        }

        // Setter
        self::$_config = array_merge(self::$_config, $cfg);
    }


    /**
     * Setup for view, used for extensibility without overriding constructor
     */
    public function init() {}


    /**
     * Layout template getter/setter
     */
    public function layout($layout = null)
    {
        if(null === $layout) {
            return $this->_layout;
        }

        $this->_layout = $layout;
        return $this;
    }


    /**
     * Load and return named block
     *
     * @param string $name Name of the block
     * @return Bullet\View\Template\Block
     */
    public function block($name, \Closure $closure = null)
    {
        if(!isset(self::$_blocks[$name])) {
            self::$_blocks[$name] = new Template\Block($name, $closure);
        }
        return self::$_blocks[$name];
    }


    /**
     * Gets a view variable
     *
     * Surpress notice errors for variables not found to
     * help make template syntax for variables simpler
     *
     * @param  string  key
     * @return mixed   value if the key is found
     * @return null    if key is not found
     */
    public function get($var, $default = null)
    {
        if(isset($this->_vars[$var])) {
            return $this->_vars[$var];
        }
        return $default;
    }


    /**
     *	Assign template variables
     *
     *	@param string key
     *	@param mixed value
     */
    public function set($key, $value='')
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if(!empty($k)) {
                    $this->_vars[$k] = $v;
                }
            }
        } else {
            if(!empty($key)) {
                $this->_vars[$key] = $value;
            }
        }
        return $this; // Fluent interface
    }


    /**
     * Gets a view variable
     */
    public function __get($var)
    {
        $this->get($var);
    }


    /**
     * Sets a view variable.
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }


    /**
     * Get template variables
     *
     * @return array
     */
    public function vars()
    {
        return $this->_vars;
    }


    /**
     * Get/Set path to look in for templates
     */
    public function path($path = null)
    {
        if(null === $path) {
            return ($this->_path) ? $this->_path : self::$_config['path'];
        } else {
            $this->_path = $path;
            $this->_exists = false;
            return $this; // Fluent interface
        }
    }


    /**
     * Get template name that was set
     *
     * @return string
     */
    public function file($view = null, $format = null)
    {
        if(null === $view) {
            return $this->_file;
        } else {
            $this->_file = $view;
            $this->_fileFormat = ($format) ? $format : self::$_config['default_format'];
            $this->_exists = false;
            return $this; // Fluent interface
        }
    }


    /**
     * Returns full template filename with format and extension
     *
     * @param OPTIONAL $template string (Name of the template to return full file format)
     * @return string
     */
    public function fileName($template = null)
    {
        if(null === $template) {
            $template = $this->file();
        }
        return $template . '.' . $this->format() . '.' . self::$_config['default_extension'];
    }


    /**
     * Get/Set layout format to use
     * Templates will use: <template>.<format>.<extension>
     * Example: index.html.php
     *
     * @param $format string (html|xml)
     */
    public function format($format = null)
    {
        if(null === $format) {
            return $this->_fileFormat;
        } else {
            $this->_fileFormat = $format;
            return $this; // Fluent interface
        }
    }


    /**
     * Escapes HTML entities
     * Use to prevent XSS attacks
     *
     * @link http://ha.ckers.org/xss.html
     */
    public function h($str)
    {
        return htmlentities($str, ENT_NOQUOTES, "UTF-8");
    }


    /**
     * Load and return new view for partial
     *
     * @param string $template Template file to use
     * @param array $vars Variables to pass to partial
     * @return Bullet\View\Template
     */
    public function partial($template, array $vars = array())
    {
        $partial = new static($template, $this->format(), $this->path());
        return $partial->set($vars);
    }


    /**
     * Verify template exists and optionally throw an exception if not
     *
     * @param boolean $throwException Throw an exception
     * @throws Bullet\View\Exception\TemplateMissing
     * @return boolean
     */
    public function exists($throw = false)
    {
        // Avoid multiple file_exists checks
        if($this->_exists) {
            return true;
        }

        $vpath    = $this->path();
        $template = $this->fileName();
        $vfile    = $vpath . $template;

        // Ensure path has been set
        if(empty($vpath)) {
            if(true === $throw) {
                throw new Exception\TemplateMissing("Base template path is not set!  Use '\$view->path('/path/to/template')' to set root path to template files!");
            }
            return false;
        }

        // Ensure template file exists
        if(!file_exists($vfile)) {
            if(true === $throw) {
                throw new Exception\TemplateMissing("The template file '" . $template . "' does not exist.<br />Path: " . $vpath);
            }
            return false;
        }

        $this->_exists = true;
        return true;
    }


    /**
     * Read template file into content string and return it
     *
     * @return string
     */
    public function content($parsePHP = true)
    {
        $this->exists(true);

        $vfile = $this->path() . $this->fileName();

        // Include() and parse PHP code
        if($parsePHP) {
            ob_start();

            // Use closure to get isolated scope
            $view = $this;
            $vars = $this->vars();
            $render = function($templateFile) use($view, $vars) {
                extract($vars);
                require $templateFile;
                return ob_get_clean();
            };
            $templateContent = $render($vfile);
        } else {
            // Just get raw file contents
            $templateContent = file_get_contents($vfile);
        }

        return trim($templateContent);
    }
}
