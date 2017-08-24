<?php

/**
 * Migration Controller Class for Codeigniter
 * 
 * This class provides a Command Line Interface
 * for developers to use Codeigniter's Migrations
 * functionality. It has been configured to only
 * respond to CLI requests hence not accessible
 * via the browser. However running it via a browser
 * is very much possible with a few tweaks in the code
 * A detailed info about the available methods
 * can be found via $this::help()
 *
 * No configurations are required before using this 
 * class as it configures everything requried by  
 * itself apart from a working database connection. 
 * 
 * @package     Migrations for Codeigniter
 * @subpackage  
 * @category    
 * @author      Muhammad Aimal <aimal.azmi.13@gmail.com>
 * @link        
 */

class Migrate extends CI_Controller
{

	/**
	 * Migration File Name
	 * 
	 * @var string
	 */
	protected $_migration_file_name = '';

	/**
	 * Migration Path
	 * 
	 * @var string
	 */
	protected $_migration_path = '';

	/**
	 * Database table with migration info
	 *
	 * @var string
	 */
	protected $_migration_table = 'migrations';

	/**
	 * Table name to be used in the migration file
	 *
	 * @var string
	 */
	protected $_migration_file_table = '';

	/**
	 * Class Version Info
	 *
	 * @var string
	 */
	protected $_class_version = '1.0';

	/**
	 * Info file
	 *
	 * @var string
	 */
	protected $_info_file_name = 'migrate_help.txt';

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		parent::__construct();

		// The migration controller will only be accessible
		// throught the CLI.
		if (is_cli() === FALSE)
		{
			show_404();
		}

		// Load the Helper classes
		$this->load->helper('file');

		// Check whether migrations are turned off
		$this->_migrations_status();

		// Load the libraries
		$this->load->library('migration');

		// Set the Migration Path to the migration folder
		$this->_migration_path = $this->config->item('migration_path');

		// Check if the application/migrations folder
		// exist and create it if it doesnot
		if ( ! is_dir($this->_migration_path))
		{
			// The migration directory wasnot found
			echo 'The application/migrations folder doesnot exist'  .  PHP_EOL;
			
			echo 'Creating the application/migrations folder...'  .  PHP_EOL;

			if ( ! mkdir($this->_migration_path))
			{
				// There was an eror creating the path
				show_error('Could not create the directory application/migrations! Try again and if the error persists try creating it manually!');
			}

			// Directory created successfully
			echo 'Directory application/migrations created successfully!'  .  PHP_EOL;
		}

		// Customize the migrations table
		// created by CI to suit our needs
		$this->_customize_migrations_table();
	}

	// --------------------------------------------------------------------

	/**
	 * Creates Migration Files
	 *
	 * @param string $file_name Migration File Name
	 * @return void
	 */
	public function index( $file_name = '' )
	{

		echo 'Enter migration file name: ';

		$file_name = trim(fgets(STDIN));

		// Check if a valid File Name was passed
		if (empty($file_name) OR $file_name === "")
		{
			show_error('Please enter a valid migration file name');
		}

		// Set the value
		$this->_migration_file_name = $file_name;

		echo 'Database Table name: ';

		$migration_file_table = trim(fgets(STDIN));

		// Check if a valid File Name was passed
		if (empty($migration_file_table) OR $migration_file_table === "")
		{
			show_error('Please enter a valid table name for the migration file!');
		}

		// Migration File table name
		$this->_migration_file_table = $migration_file_table;

		// Format the file name
		$file_name = $this->_format_file_name($file_name);

		// Get the migration version
		$migration_version = $this->_get_migration_version();

		// Append the migration number/timestamp
		// to the file name
		$file_full_name = $migration_version  .  '_'  .  $file_name  .  '.php';

		// Create the complete path to the file
		$file = $this->_migration_path  .  $file_full_name;

		// Get the default migration file content
		$file_content = $this->_get_file_content($file_name);

		// Create the file and throw error if could not create
		if ( ! write_file($file, $file_content))
		{
			// Error creating the file
			$error = 'Could not create file!'  . PHP_EOL;
			
			show_error($error);
		}

		// Update the migration version in Schema
		$this->_update_latest_file_version($migration_version);

		// File created successfully
		echo 'Migration File "'  .  $file_full_name  .  '" successfully created!'  .  PHP_EOL;

		return;
	}

	// --------------------------------------------------------------------

	/**
	 * Calls the Migration::current()
	 *
	 * @return void
	 */
	public function current()
	{
		if ($this->migration->current() === FALSE)
        {
            show_error($this->migration->error_string());
        }

        echo "Migrated to the current version!";
	}

	// --------------------------------------------------------------------

	/**
	 * Calls the Migration::latest()
	 *
	 * @return void
	 */
	public function latest()
	{
		if ($this->migration->latest() === FALSE)
        {
            show_error($this->migration->error_string());
        }

        echo "Migrated to the latest version!";
	}

	// --------------------------------------------------------------------

	/**
	 * Rollback to a migration version
	 *
	 * @param int $version Rollback Migration Version
	 * @return void
	 */
	public function rollback($version = 0)
	{
		if ($this->migration->version(abs($version)) === FALSE)
        {
            show_error($this->migration->error_string());
        }

        echo "Migrated to the version - "  . $version;
	}

	// --------------------------------------------------------------------

	/**
	 * Resets the migration table and
	 * Deletes all the migartion files
	 * Deletes all the tables created
	 * by the migrations aswell
	 *
	 * @param bool $rollback rollback to zero
	 * @return void
	 */
	public function reset($rollback = TRUE)
	{
		$this->_prompt("This function will delete all the files in the migrations folder,".
			" delete all relevant tables and also reset the migrations table in DB.");

		// Rollback migrations to version '0'
		if (($rollback === TRUE) && ($this->db->table_exists($this->_migration_table) === TRUE))
		{
			if ($this->rollback(0) === FALSE)
			{
				show_error("There was an error resetting the migrations!");
			}
		}

		// DROP the migartions table
		if ($this->dbforge->drop_table($this->_migration_table,TRUE) === FALSE )
		{
			show_error("Table 'Migrations' could not be deleted!");
		}

		//Delete all the migartion files
		if (delete_files($this->_migration_path) === FALSE)
		{
			show_error("Could not all the migration files!");
		}
		
		echo "Migartions have been reset!";
	}

	// --------------------------------------------------------------------

	/**
	 * Rollbacks the migrations one step back
	 *
	 * @return void
	 */
	public function last()
	{
		$rollback_version = $this->_get_version() == 0 ? 0 : (int) ($this->_get_version() - 1);

		if ($rollback_version)
		{
			$this->rollback($rollback_version);
		}
		else
		{
			show_error("Looks like your migrations table hasn't been configured properly!" .
					" Try reseting the migarations using migrate::reset() and try again!");
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Information about using this class
	 *
	 * @return void
	 */
	public function help()
	{
		if ( ! file_exists(__DIR__  .  '/'  .  $this->_info_file_name))
			show_error("Couldn't find the info file!");

		$file_content = file_get_contents(__DIR__  .  '/'  .  $this->_info_file_name);

		if ($file_content)
		{
			$file_content = str_replace('#', $this->_class_version, $file_content);
			echo $file_content;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Information about the migrations
	 *
	 * @return void
	 */
	public function info()
	{
		$content = 'No migrations found!';

		$headings = ['Ran','Migration'];

		$migrations = get_filenames($this->_migration_path);

		if ((is_array($migrations)) && (count($migrations) > 0))
		{
			$row_width = 0;
			$content = '';
			$header = '';
			$rows = [];
			$version = $this->_get_version('version');

			foreach($headings as $key => $value)
			{
				$header .= '| '  .  $value  .  ' ';
			}

			foreach ($migrations as $key => $value) 
			{
				if ( ! empty($value))
				{	
					$string = '| T   | ';

					if ( (int) $this->_get_migration_number($value) > $version)
					{
						$string = '| F   | ';
					}

					$string = $string  .  str_replace('.php', '', $value)  .  ' ';
					$rows[] = $string;
					$row_width = (strlen($string) > $row_width) ? strlen($string) : $row_width;
				}
			}

			if (count($rows) > 0)
			{
				foreach ($rows as $row)
				{
					$content .= str_pad($row, $row_width, " ", STR_PAD_RIGHT)  .  '|'  .  PHP_EOL;
				}

				// Lower layer
				$content = $content  .  '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL;
			}

			// Paddings
			$header = str_pad($header, $row_width, " ", STR_PAD_RIGHT)  .  '|'  .  PHP_EOL;

			// Upper layer
			$header = '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL  .  $header;

			// Lower layer
			$header = $header  .  '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL;

			// Mix it up
			$content = PHP_EOL  .  $header  .  $content;
		}

		echo $content;
	}

	// --------------------------------------------------------------------

	/**
	 * Checks whether the migrations are
	 * ENABLED or DISABLED in the application
	 * Enables if disabled after user's permission
	 *
	 * @return void
	 */
	protected function _migrations_status()
	{	
		$migration_config_file = APPPATH  .  'config/migration.php';

		// Check if config/migration.php exists
		if ( ! file_exists($migration_config_file))
		{
			show_error("It seems the config/migration.php file doesnot exist. That file is necessary!");
		}

		// Migrations Config File exists, load it
		$this->config->load('migration');

		// Migration Config file exists 
		// and migrations are enabled
		if ($this->config->item('migration_enabled') === TRUE)
			return;

		// Recheck if the migrations aren't enabled
		if ($this->config->item('migration_enabled') !== TRUE)
		{
			echo PHP_EOL  .  'It looks like the migrations have been disabled in the config file.'  .  PHP_EOL;

			// Ask to enable migrations
			$this->_prompt("Enable migrations?");

			$migration_file_content = file_get_contents($migration_config_file);

			if ( ! empty($migration_file_content))
			{
				$migration_file_content = str_replace("config['migration_enabled'] = FALSE", "config['migration_enabled'] = TRUE", $migration_file_content);
				
				// write the changes to the file
				if ( ! write_file($migration_config_file,$migration_file_content))
				{
					$error = 'There was an error while configuring changes in the config/migration.php file!';
					$error .= PHP_EOL  .  'If the error persists simply goto projectfolder/application/config/migration.php'  .  PHP_EOL;
					$error .= ' and set $'.'config[migration_enabled] = TRUE';
					show_error($error);
				}

				// Reload configuration
				echo PHP_EOL  .  'Reloading Configurations...'  .  PHP_EOL;
				$this->config->set_item('migration_enabled',TRUE);
				
				// Configurations were changed successfully
				if ($this->config->item('migration_enabled') === TRUE)
				{
					echo PHP_EOL  .  'Migrations Enabled!'  .  PHP_EOL  .  PHP_EOL;
					return;
				}	
			}

			// If the program has reached till here it
			// means that the migration configurations
			// were not set properly at all
			show_error("Looks like your migration configurations aren't set properly. Try getting a fresh copy of config/migration.php and retry!");
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Performs regex and only allows
	 * letters and underscore.
	 *
	 * @return Mixed. File name on success, show_error() on wrong file name
	 */
	protected function _customize_migrations_table()
	{
		// The CI will take care of validating 
		// and creating the table. We only need
		// to alter it to add 'latest_file_version'
		// in the table 

		// Check if the field has already
		// been added to the table
		if ($this->db->field_exists('latest_file_version',$this->_migration_table) === TRUE)
		{
			return;
		}

		$fields = array(
		        'latest_file_version' => array('type' => 'INT','default' => 0)
		);

		if ($this->dbforge->add_column($this->_migration_table, $fields) === FALSE)
		{
			show_error("Coud not add field 'latest_file_version' to the migrations table!");
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Replaces spaces with '_'
	 *
	 * @return string
	 */
	protected function _format_file_name($file_name = '')
	{
		return str_replace(' ', '_', $file_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the default code found in the
	 * migration file. The best way to do this
	 * is not from a function but since I didn't
	 * want to create an extra file as sometimes 
	 * it causes developers the hassle to locate
	 * and validate the files and everything. 
	 *
	 * However I recommend cutting $file_content
	 * from the function and and pasting it to a 
	 * file and then returning it from there so 
	 * that this controller doesnt contain this 
	 * extra bit of code
	 *
	 * @param string $file_name Name of Migration File
	 * @return string 
	 */
	protected function _get_file_content($file_name = '')
	{
		// Php opening tag
		$file_content = '<?php'  .  PHP_EOL  .  PHP_EOL;	// <?php
		$file_content .= 'defined(\'BASEPATH\') OR exit(\'No direct script access allowed\');'  .  PHP_EOL;

		// Class
		$file_content .= PHP_EOL  .  'class Migration_'  .  $file_name;	// class File_name

		$file_content .= ' extends CI_Migration {'  .  PHP_EOL  .  PHP_EOL;	// extends CI_Migration {

		// Private string table name
		$file_content .= '	/**'  .  PHP_EOL;
		$file_content .= '	 * Name of the table to be used in this migration!'  .  PHP_EOL;
		$file_content .= '	 *'  .  PHP_EOL;
		$file_content .= '	 * @var string'  .  PHP_EOL;
		$file_content .= '	 */'  .  PHP_EOL;
		$file_content .= '	protected $_'.'table_name = "'  . trim($this->_migration_file_table) .  '";'  .  PHP_EOL  . PHP_EOL;

		// Public function up content
		$file_content .= '	public function up()'  .  PHP_EOL;
		$file_content .= '	{'  .  PHP_EOL;

		if (strpos($this->_migration_file_name, 'modify') !== FALSE)
		{
			$file_content .= '		$this->dbforge->add_column($'.'this->_table_name, $'.'this->_fields());'  .  PHP_EOL;
		}
		else
		{
			$file_content .= '		$this->dbforge->add_field(\'id\');'  .  PHP_EOL;
			$file_content .= '		$this->dbforge->add_field("`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");'  .  PHP_EOL;
			$file_content .= '		$this->dbforge->add_field("`updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");'  .  PHP_EOL;
			$file_content .= '		$this->dbforge->create_table($'.'this->_table_name, TRUE);'  .  PHP_EOL;
		}

		$file_content .= '	}'  .  PHP_EOL;

		$file_content .= PHP_EOL;

		// Public function down content
		$file_content .= '	public function down()'  .  PHP_EOL;
		$file_content .= '	{'  .  PHP_EOL;

		if (strpos($this->_migration_file_name, 'modify') !== FALSE)
		{
			$file_content .= '		if (is_array($'.'this->_fields()))'  .  PHP_EOL;
			$file_content .= '		{'  .  PHP_EOL;
			$file_content .= '			foreach($this->_fields() as $key => $val)'  .  PHP_EOL;
			$file_content .= '			{'  .  PHP_EOL;
			$file_content .= '				$this->dbforge->drop_column($'.'this->_table_name,$'.'key);'  .  PHP_EOL;
			$file_content .= '			}'  .  PHP_EOL;
			$file_content .= '		}'  .  PHP_EOL;
		}
		else
		{
			$file_content .= '		$this->dbforge->drop_table($'.'this->_table_name, TRUE);'  .  PHP_EOL;
		}

		$file_content .= '	}'  .  PHP_EOL;

		// The file name consists modify
		if (strpos($this->_migration_file_name, 'modify') !== FALSE)
		{	
			$file_content .= PHP_EOL;
			$file_content .= '	/**'  .  PHP_EOL;
			$file_content .= '	 * Returns an array of the fields to be used within the up and down functions!'  .  PHP_EOL;
			$file_content .= '	 *'  .  PHP_EOL;
			$file_content .= '	 * @return array'  .  PHP_EOL;
			$file_content .= '	 */'  .  PHP_EOL;
			$file_content .= '	protected function _fields()'  .  PHP_EOL;
			$file_content .= '	{'  .  PHP_EOL;
			$file_content .= '		return array();'  .  PHP_EOL;
			$file_content .= '	}'  .  PHP_EOL;
		}
		
		// Class closing tag
		$file_content .= PHP_EOL  .  '}'  .  PHP_EOL  .  PHP_EOL;	// }

		// Php closing tag
		$file_content .= '?>';

		return $file_content;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the latest migration version number
	 *
	 * @return int $migration_version Migration Version
	 */
	protected function _get_migration_version()
	{
		// By default we'll use the timestamps
		// for version control
		$migration_version = date("YmdHis");

		// If the migration type has been set to 
		// sequential, we'll set the migration 
		// version to sequential
		if ($this->config->item('migration_type') === 'sequential')
		{
			$migration_version = (int) ( (int) $this->_get_version() + 1);
			$migration_version = str_pad($migration_version, 3, "0", STR_PAD_LEFT);
		}

		return $migration_version;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the latest file version
	 * from the migrations table
	 *
	 * @return	int	last migration file version
	 * @param string $select Name of row to return
	 */
	protected function _get_version($select = 'latest_file_version')
	{
		$row = $this->db->select($select)->get($this->_migration_table)->row();
		return $row ? $row->$select : '0';
	}

	// --------------------------------------------------------------------

	/**
	 * Stores the latest file version
	 *
	 * @param	string	$migration	Migration reached
	 * @return	void
	 */
	protected function _update_latest_file_version($file_version)
	{
		$this->db->update($this->_migration_table, array(
			'latest_file_version' => $file_version
		));
	}

	// --------------------------------------------------------------------

	/**
	 * Revere the migration type to the 
	 * given type. i.e. if the migration
	 * files were sequenced through timestamps
	 */
	protected function _reverse_migration_type()
	{
		// Later
	}

	// --------------------------------------------------------------------

	/**
	 * Prompt Function
	 *
	 * @return void
	 */
	protected function _prompt($msg = '')
	{
		echo PHP_EOL  .  $msg  .  PHP_EOL;
		echo "Continue? (Y/N) - ";

		$stdin = fopen('php://stdin', 'r');
		$response = fgetc($stdin);
		if ($response != 'Y' && $response != 'y') {
		   echo "Opertaion Terminated!.\n";
		   exit;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Extracts the migration number from a filename
	 *
	 * @param	string	$migration
	 * @return	string	Numeric portion of a migration filename
	 */
	protected function _get_migration_number($migration)
	{
		return sscanf($migration, '%[0-9]+', $number)
			? $number : '0';
	}

	// --------------------------------------------------------------------

}

?>
