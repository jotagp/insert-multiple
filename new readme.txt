Quando temos um grande volume de dados para inserir num banco, faze-lo de forma rápida torna-se uma tarefa difícil. 
Isso por que, por comodidade, tendemos a realizar n operações de insert, sendo n a quantidade dos registros, exemplo:

php something like:
foreach ($persons as $person) {

  $insert = "INSERT INTO persons (name, nick, mail) VALUES ('{$person['name']}', '{$person['nick']}', '{$person['mail']}')";
  $connection->query($insert) or die($connection->error);

}

sql something like:
INSERT INTO persons(name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com');
INSERT INTO persons(name, nick, mail) VALUES ('Maria', 'Mari', 'maria@mail.com');
[...]

Essa abordagem resolve o problema. Mas o tempo de execução deste script será lento (considerando digamos um milhão de registros).
Isto por que, para cada instrução, haverá uma transação no banco, e isto implica em uma ida e vinda ao seu disco rigido.
Uma "opção" que ajuda um pouco, é controlar a transação manualmente, exemplo:

php something like:
$coonection->begin_transaction();
foreach ($persons as $person) {

  $insert = "INSERT INTO persons (name, nick, mail) VALUES ('{$person['name']}', '{$person['nick']}', '{$person['mail']}')";
  $connection->query($insert) or die($connection->error);

}
$connection->commit();

sql something like:
START TRANSACTION;
INSERT INTO persons(name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com');
INSERT INTO persons(name, nick, mail) VALUES ('Maria', 'Mari', 'maria@mail.com');
[...]
COMMIT;

Agora, ganhariamos um pouco de tempo, pois o numero de transações diminui.
No entanto, o numero de instruções continua em n, sendo n a quantidade de registros.
Poderiamos fazer então, uma instrução com multiplos registros, ex:

php something like:
$coonection->begin_transaction();
$insert = "INSERT INTO persons (name, nick, mail) VALUES";
foreach ($persons as $person) {

  $values[] = " ('{$person['name']}', '{$person['nick']}', '{$person['mail']}')";

}
$insert .= implode(", ", $values); // join values separated by comma
$connection->query($insert) or die($connection->error);
$connection->commit();

sql something like:
START TRANSACTION;
INSERT INTO persons(name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com'), ('Maria', 'Mari', 'maria@mail.com'), [...];

COMMIT;