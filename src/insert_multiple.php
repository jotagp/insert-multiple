<?php


namespace jotagp\insert_multiple;


class insert_multiple {
  

  public $table_properties = []; // array que guarda as propriedades da tabela em questão
  public $table_name = []; // string que guarda o nome da tabela em questão
  public $final_array = []; // array de arrays, com todos os registros a serem inseridos
  public $connection = []; // conecção com o banco
  public $debug = false; // flag para exibir alguns echos etc


  // constructor
  public function __construct($connection, $table_name) {


    // setando os atributos do objeto
    $this->connection = $connection;
    $this->table_name = $table_name;

    // buscando no banco pela tabela em questão
    $selectMetadata = "DESC {$table_name}";
    $rows = $connection->query($selectMetadata) or die("\nErro ao buscar metadados. ". $connection->errno . " - " . $connection->error);
    
    // valida se foram encontrados dados
    if ($rows->num_rows > 0) {

      // percorre as tuplas
      foreach ($rows as $row) {

        $column = $row['Field'];

        if ($row['Key'] == 'PRI') continue;

        foreach ($row as $key => $val) {

          $this->table_properties[$column][$key] = $val;

        }

      }
      
    }

  }


  // function do add new value into insert
  public function push($any) {
    
    // echo "\npush";

    // global $table_properties;
    // global $final_array;
    

    $new_any = [];

    // percorrendo as propriedades da tabela
    foreach ($this->table_properties as $key => $val) {

      // verificando se existe algum atributo informado no parametro, contido na tabela
      if (isset($any[$key])) {

        // se sim

        // verifica se o valor é valido
        if (strlen($any[$key]) > 0) {

          // se sim
          $new_any[$key] = addslashes($any[$key]);

        }
        else {

          //senão
          $new_any[$key] = $val['Default'];

        }

      }
      else {

        //senão
        $new_any[$key] = $val['Default'];

      }

      if (1==1) {true;} // implementar aqui a validação da tipagem, para não concatenar as aspas segamente

    }

    // incrementa o array de arrays
    $this->final_array[] = "'". implode("', '", $new_any) ."'";
    
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

    echo "\nconfig";
    if (isset($any['debug'])) $this->debug = $any['debug'];
    
  }


  // destructor
  public function __destruct() {
  
    echo "\ndestruct";
    // echo $this->table_name;
    // var_dump($this->connection);

  }


}


?>