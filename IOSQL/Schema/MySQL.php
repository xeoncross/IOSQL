<?php
/**
 * Provides a wrapper around the MySQL schema.
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL\Schema;

class MySQL extends Schema
{
	public $sql = array(

		'create_table' => "CREATE TABLE `%s` (\n%s,\nPRIMARY KEY (`%s`)\n) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
		'drop_table' => 'DROP TABLE IF EXISTS `%s` %s;',
		'rename_table' => 'ALTER TABLE `%s` RENAME TO `%s`;',

		'create_column' => 'ALTER TABLE `%s` ADD COLUMN %s;',
		'drop_column' => 'ALTER TABLE `%s` DROP COLUMN `%s` %s;',
		'rename_column' => 'ALTER TABLE `%s` RENAME COLUMN `%s` to `%s`;',

		'create_foreign_key' => 'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE CASCADE ON UPDATE CASCADE;',
		'drop_foreign_key' => 'ALTER TABLE `%s` DROP CONSTRAINT `%s` %s;',

		'create_unique' => 'ALTER TABLE `%s` ADD CONSTRAINT `%s` UNIQUE (`%s`);',
		'drop_unique' => 'ALTER TABLE `%s` DROP CONSTRAINT `%s` %s;',

		'create_index' => 'CREATE INDEX `%s` USING BTREE ON `%s` (`%s`);',
		'drop_index' => 'DROP INDEX IF EXISTS `%s` %s;',
	);


	public function column($column, array $options = array())
	{

		$data = self::column_options($options);

		$type = $data['type'];

		// Integer?
		if($type == 'primary' OR $type == 'integer')
		{
			// Default to int
			$length = $data['length'] ? $data['length'] : 2147483647;

			if($length <= 127)
				$type = 'TINYINT';
			elseif($length <= 32767)
				$type = 'SMALLINT';
			elseif($length <= 8388607)
				$type = 'MEDIUMINT';
			elseif($length <= 2147483647)
				$type = 'INT';
			else
				$type = 'BIGINT';

			// Is this the primary column?
			if($data['type'] == 'primary')
			{
				return "`$column` $type NOT NULL AUTO_INCREMENT";
			}
		}
		elseif($type == 'string')
		{
			// Default to text
			$length = $data['length'] ? $data['length'] : 65535;

			// MySQL 5.0.3+ now supports longer varchar
			if($length < 65535)
				$type = 'VARCHAR('. $length.')';
			elseif($length == 65535)
				$type = 'TEXT';
			elseif($length <= 16777215)
				$type = 'MEDIUMTEXT';
			else
				$type = 'LONGTEXT';
		}
		elseif($type == 'boolean')
		{
			$type = 'TINYINT(1)';
		}
		elseif($type == 'decimal')
		{
			$type = 'DECIMAL('. $data['precision'].','. $data['scale'].')';
		}
		else
		{
			$type = 'DATETIME';
		}

		// Build Column Definition
		$sql = "`$column` $type";

		// Text/blob cannot contain defaults
		if($type === 'TEXT')
		{
			if( ! $data['null']) $sql .= ' NOT NULL';
			
			return $sql;
		}

		/*
		// Varchar can't handle default (but can handle null values)
		if($data['type'] !== 'string')
		{
		}
		*/

		// '' and FALSE are both valid defaults
		if($data['default'] !== NULL)
		{
			$sql .= ' DEFAULT ';

			if(is_bool($data['default']))
			{
				$sql .= $data['default'] ? 'true' : 'false';
			}
			if(is_string($data['default']))
			{
				$sql .= "'" . $data['default'] . "'";
			}
			else
			{
				$sql .= $data['default'];
			}
		}

		if( ! $data['null']) $sql .= ' NOT NULL';

		return $sql;
	}

}
