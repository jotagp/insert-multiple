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

    // A variavel insertSizeLimit armazena o tamanho maximo em bites de uma transação permitida pelo mysql
    // E subtraimos dela 10%, para uma margem de erro
    $insertSizeLimit = $this->connection->query("show variables like 'max_allowed_packet'")->fetch_assoc()['Value'];
    $insertSizeLimit -= ($insertSizeLimit * 0.1);

    // A variavel currentSize armazena o valor em bites de cada tupla
    $currentSize = 0;

    // A variavel partition será a quantidade de inserts multiplos que acontecerão...
    // Toda vez que a currentSize estiver próxima do insertSizeLimit, uma nova partição é criada
    $partition = 0;

    // Será o array final, com os dados tratados. Cada indice será um valor da variavel partition
    $inserts = [];

    /*
    PRIMEIRA ETAPA:
    Dividir o array final_array, em arrays menores, que caibam num insert
    */
    foreach ($this->final_array as $index => $array) {

      // Incrementa a currentSize, com os bites da tulpa iterada
      $currentSize += mb_strlen($array, '8bit');

      // Se a flag debug for verdadeira nos parametros da função, o insert deverá acontecer de uma em uma tupla
      $currentSize = $this->debug ? 1 : $currentSize;
      $insertSizeLimit = $this->debug ? 1 : $insertSizeLimit;

      // Enquanto os currentSize forem menor ou igual ao insertSizeLimit, inserimos a tupla na mesma particao
      if ($currentSize <= $insertSizeLimit) {

        $inserts[$partition][$index] = $array;

        // caso o debug seja true, cria uma partição para cada tupla
        if ($this->debug) {

          $partition += 1;

        }

      }
      else { // Se não, inserimos a tupla numa nova particao

        $partition += 1;

        // Inicia novamente o valor da variavel currentSize, com os bites da tupla atual
        $currentSize = mb_strlen($array, '8bit');
        $inserts[$partition][$index] = $array;

      }

    }

    /*
    SEGUNDA ETAPA:
    Concatenar todas as posições dentro da partição, em uma única posição na partição
    */
    foreach ($inserts as $index => $insert) {

      $insert = join('), (', $insert);
      $inserts[$index] = $insert;

    }

    
    /*
    ÚLTIMA ETAPA:
    Inserir os dados
    */
    $this->connection->begin_transaction();

    // try {

      // if (!$this->debug) {
        
      //   // echoLog(" ", FALSE);

      // }

      foreach ($inserts as $partition => $values) {

        // $id = is_numeric($values['0']) ? 'id,' : '';
        
        $columns = implode(", ", array_keys($this->table_properties));
        $insertQuery = sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->table_name, $columns, $values);

        // if (strstr($insertQuery, 'NULL') == TRUE) {

        //   $insertQuery = str_replace('\'NULL\'', 'NULL', $insertQuery);

        // }

        try {

          $this->connection->query($insertQuery) or die($this->connection->error . '' . $insertQuery);

        }
        catch (\Exception $e) {

          // echoLog("\nErro ao tentar gravar Tags: {$e->getMessage()}\n");
          exit;

        }

        if (!$this->debug) {

          // echoLog("P", FALSE);

        }

      }

      $this->connection->commit();

    // }
    // catch (mysqli_sql_exception $exception) {

    //   $connection->rollback();
    //   throw $exception;

    // }
    
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