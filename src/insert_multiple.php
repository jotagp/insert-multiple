<?php


namespace jotagp\insert_multiple;


class insert_multiple {
  

  public $table_properties = []; // array that holds the table details
  public $table_name = []; // string that holds the name of the table
  public $final_array = []; // array of arrays, with all data
  public $connection = []; // connection with database mysql/mariadb
  public $debug = false; // flag to debug. insert one record per time


  // constructor
  public function __construct($connection, $table_name) {


    // set the object attributes
    $this->connection = $connection;
    $this->table_name = $table_name;


    // fetch the table details from the database 
    $selectMetadata = "DESC {$table_name}";
    $rows = $connection->query($selectMetadata) or die("\nError: could not fetch metadata. ". $connection->errno . " - " . $connection->error);
    
    // checks if the data was found
    if ($rows->num_rows > 0) {

      // iterate over the tuples
      foreach ($rows as $row) {

        // that variable contains the column name 
        $column_name = $row['Field'];

        // go to next iteration, if that is a primary key autoinrecemnt (keep database decision)
        if ($row['Key'] == 'PRI' && $row['Extra'] == 'auto_increment') continue;

        // make array with the table properties
        foreach ($row as $key => $val) {

          // keep in type property, only strings between a and z 
          if ($key == 'Type') {
            
            $val = preg_replace('/[^a-zA-Z]+/', '', $val);
            
          }

          $this->table_properties[$column_name][$key] = $val;

        }

      }
      
    }
    else {

      echo "\nCould not fetch metadata";
      return FALSE;

    }

  }


  // function do add new value into insert
  public function push($any) {
    
    // temporary array to store that data
    $new_any = [];

    // iterate over the table properties
    foreach ($this->table_properties as $column_name => $property) {

      // check if exists at variable any and if is not empty, this iteration column name  
      if (isset($any[$column_name]) && !empty($any[$column_name])) {

        $new_any[$column_name] = addslashes($any[$column_name]);

      }
      else {

        $new_any[$column_name] = $property['Default'];

      }

      // concat the quoation marks, if necessary
      $string_types = ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'BLOB', 'MEDIUMTEXT', 'MEDIUMBLOB', 'LONGTEXT', 'LONGBLOB', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR'];

      if (in_array(strtoupper($property['Type']), $string_types)) {

        $new_any[$column_name] = "'". $new_any[$column_name] . "'";

      }

    }

    // increment the final array
    // $this->final_array[] = "'". implode("', '", $new_any) ."'";
    $this->final_array[] = implode(", ", $new_any);
    
  }


  // function to run insert
  public function exec() {


    // The variable insert_size_limit stores the maximum size in bytes of a transaction allowed by mysql
    $insert_size_limit = $this->connection->query("show variables like 'max_allowed_packet'")->fetch_assoc()['Value'];

    // And we subtract 10% from it, for a margin of error
    $insert_size_limit -= ($insert_size_limit * 0.1);

    // The variable current_size stores the value in bytes of each tuple
    $current_size = 0;

    // The Partition variable will be the amount of multiple inserts that will happen
    $partition = 0;

    // It will be the final array, with the processed data. Each index will be a value of the Partition variable
    $inserts = [];

    /*
    FIRST STEP:
    Split the final_array array into smaller arrays that fit in an insert
    */
    foreach ($this->final_array as $index => $array) {

      // Increments the current_size, with the bytes of the iterated tulpa
      $current_size += mb_strlen($array, '8bit');

      // If the debug flag is true on the function parameters, the insertion must happen one by one tuple
      $current_size = $this->debug ? 1 : $current_size;
      $insert_size_limit = $this->debug ? 1 : $insert_size_limit;

      // As long as current_size is less than or equal to insert_size_limit, we insert the tuple in the same partition, else, we insert the tuple in a new partition.
      if ($current_size <= $insert_size_limit) {

        $inserts[$partition][$index] = $array;

        // if debug is true, create a partition for each tuple
        if ($this->debug) {

          // Every time current_size is close to insert_size_limit, a new partition is created
          $partition += 1;

        }

      }
      else {

        $partition += 1;

        // Initializes the value of the variable current_size again, with the bytes of the current tuple
        $current_size = mb_strlen($array, '8bit');
        $inserts[$partition][$index] = $array;

      }

    }

    /*
    SECOND STEP:
    Concatenate all positions within the partition, into a single position within the partition
    */
    foreach ($inserts as $index => $insert) {

      $insert = join('), (', $insert);
      $inserts[$index] = $insert;

    }

    
    /*
    LAST STEP:
    Insert the data
    */
    $this->connection->begin_transaction();


    foreach ($inserts as $partition => $values) {
      
      
      $columns = implode(", ", array_keys($this->table_properties));
      $insert_query = sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->table_name, $columns, $values);

      // if (strstr($insert_query, 'NULL') == TRUE) {

      //   $insert_query = str_replace('\'NULL\'', 'NULL', $insert_query);

      // }

      try {

        $this->connection->query($insert_query) or die($this->connection->error . '' . $insert_query);

      }
      catch (\Exception $e) {

        die("\nError");

      }


    }


    $this->connection->commit();

    
  }

  
  // function to set configs
  public function config($any) {

    // echo "\nconfig";
    if (isset($any['debug'])) $this->debug = $any['debug'];
    
  }


  // destructor
  public function __destruct() {
  
    // echo "\ndestruct";
    // echo $this->table_name;
    // var_dump($this->connection);

  }


}


?>