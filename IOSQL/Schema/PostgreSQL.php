<?php
/**
 * Provides a wrapper around the PostgreSQL schema. NOT WORKING.
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL\Schema;

class PostgreSQL extends Schema
{
	// http://www.postgresql.org/docs/8.4/static/sql-altertable.html
	// http://www.postgresql.org/docs/8.4/static/ddl-alter.html#AEN2534
	// http://www.postgresql.org/docs/8.4/static/sql-createtable.html
	public $sql = array(

		// PostgreSQL 9.1!
		//'create_table' => "CREATE TABLE IF NOT EXISTS %s (\n%s\n);",
		//'drop_column' => 'ALTER TABLE %s DROP COLUMN IF EXISTS %s RESTRICT;',
		//'drop_column_cascade' => 'ALTER TABLE %s DROP COLUMN IF EXISTS %s CASCADE;',

		'create_table' => "CREATE TABLE \"%s\" (\n%s\n);",
		'drop_table' => 'DROP TABLE IF EXISTS "%s" %s;',
		'rename_table' => 'ALTER TABLE "%s" RENAME TO "%s";',

		'create_column' => 'ALTER TABLE "%s" ADD COLUMN %s;',
		'drop_column' => 'ALTER TABLE "%s" DROP COLUMN "%s" %s;',
		'rename_column' => 'ALTER TABLE "%s" RENAME COLUMN "%s" to "%s";',

		'create_foreign_key' => 'ALTER TABLE "%s" ADD CONSTRAINT "%s" FOREIGN KEY ("%s") REFERENCES "%s" ("%s") ON DELETE CASCADE ON UPDATE CASCADE;',
		'drop_foreign_key' => 'ALTER TABLE "%s" DROP CONSTRAINT "%s" %s;',

		'create_unique' => 'ALTER TABLE "%s" ADD CONSTRAINT "%s" UNIQUE ("%s");',
		'drop_unique' => 'ALTER TABLE "%s" DROP CONSTRAINT "%s" %s;',

		'create_index' => 'CREATE INDEX "%s" ON "%s" USING btree ("%s");',
		'drop_index' => 'DROP INDEX IF EXISTS "%s" %s;',
	);


	public function column($column, array $options = array())
	{
		$data = self::column_options($options);

		$type = $data['type'];

		// Integer?
		if($type == 'primary' OR $type == 'integer')
		{
			$length = $data['length'] ? $data['length'] : 10000000000;

			if($length <= 32767)
			{
				$type = 'smallint';
			}
			elseif($length <= 2147483647)
			{
				$type = 'integer';
			}
			else
			{
				$type = 'bigint';
			}

			if($data['type'] == 'primary')
			{
				return "\"$column\" bigserial primary key";
			}
		}
		elseif($type == 'string')
		{
			// Even if "text" isn't a valid type in SQL
			// PostgreSQL treats it the same as "character varying" (i.e. "varchar")
			$type = 'text';
		}
		elseif($type == 'boolean')
		{
			$type = 'boolean';
		}
		elseif($type == 'decimal')
		{
			$type = 'decimal('. $data['precision'].','. $data['scale'].')';
		}
		else
		{
			$type = 'timestamp without time zone';
		}

		// Build Column Definition
		$sql = "\"$column\" $type";

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

		// PostgreSQL defaults to NULL for all columns
		if( ! $data['null']) $sql .= ' NOT NULL';

		return $sql;
	}

}
