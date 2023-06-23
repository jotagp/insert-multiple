# MULTI-UPSERT  


## Summary  

1. [Introduction](#anchor1)  
	1.1 [Why](#anchor2)  
	1.2 [Licence](#anchor3)  
2. [Get Started](#anchor4)  
	2.1 [Dependences](#anchor5)  
	2.2 [Instalation](#anchor6)  
	2.3 [Methods](#anchor7)  
3. [Usage](#anchor8)  
	3.1 [MySQL/MariaDB](#anchor9)  
	3.2 [Postgres](#anchor10) (Cooming soon)  
	3.3 [SQL-Server](#anchor11) (Cooming soon)  
3. [Enjoy](#anchor12)  


<a id="anchor1"></a>
## 1. Introduction  


<a id="anchor2"></a>
**1.1 Why**  

When we have a large volume of data to insert or update in a database, doing it quickly becomes a difficult task. This is because, for convenience, we can perform N insert/update operations, where N is the number of records, for example:


``` php
// php something like:
foreach ($persons as $person) {

  $insert = "INSERT INTO persons (name, nick, mail) VALUES ('{$person['name']}', '{$person['nick']}', '{$person['mail']}')";
  $connection->query($insert) or die($connection->error);

}
```
``` sql
-- sql something like:
INSERT INTO persons (name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com');
INSERT INTO persons (name, nick, mail) VALUES ('Maria', 'Mari', 'maria@mail.com');
[...]
```

This approach solves the problem of entering data. But the execution time of this script will be slow (given say a million records). This is because, for each instruction, there will be a transaction in the database, and this implies going back and forth to your hard drive.

---

An "option" that helps a little is to control the transaction manually, for example:

```php
// php something like:
$connection->begin_transaction();
foreach ($persons as $person) {

  $insert = "INSERT INTO persons (name, nick, mail) VALUES ('{$person['name']}', '{$person['nick']}', '{$person['mail']}')";
  $connection->query($insert) or die($connection->error);

}
$connection->commit();
```
```sql
-- sql something like:
START TRANSACTION;
INSERT INTO persons (name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com');
INSERT INTO persons (name, nick, mail) VALUES ('Maria', 'Mari', 'maria@mail.com');
[...]
COMMIT;
```

Now, we would gain a little time, as the number of transactions decreases.
However, the number of instructions remains at N, with N being the number of registers.

---

We could then make an instruction with multiple registers, eg:


```php
// php something like:
$insert = "INSERT INTO persons (name, nick, mail) VALUES";
foreach ($persons as $person) {

  $values[] = " ('{$person['name']}', '{$person['nick']}', '{$person['mail']}')";

}
$insert .= implode(", ", $values); // join values separated by comma
$connection->query($insert) or die($connection->error);
```
```sql
-- sql something like:
INSERT INTO persons (name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com'), ('Maria', 'Mari', 'maria@mail.com'), [...];
```

This way the execution time will be ridiculously less. However, it is common for banks to have a setting that limits the size of a transaction. And it may be that you reach this size (after all, remember that we are considering a million records). 

---

So the ideal would be to divide the number of registers into a few multiple instructions. Example, considering without a thousand records, we could divide them into 10 instructions/transactions, each with 10 thousand records:

```sql
-- sql something like:
INSERT INTO persons (name, nick, mail) VALUES ('João', 'Jota', 'joao@mail.com'), ('Maria', 'Mari', 'maria@mail.com'), [...];
INSERT INTO persons (name, nick, mail) VALUES ('Ana', 'Ana', 'ana@mail.com'), ('Francisco', 'Chico', 'francisco@mail.com'), [...];
[...]
```

Cool, but how to implement such a solution with PHP? And that's where we come in! Without much effort you can solve this problem, example:

```php
// php something like
$upsert = new upsert($connection, "persons");
foreach ($persons as $person) {
  
  $upsert->push($person); // concat new values

}
$upsert->exec(); // run inserts
```

<a id="anchor3"></a>
**1.3 License**  
This code is licensed under the [MIT license](https://opensource.org/licenses/MIT).  


<a id="anchor4"></a>
## 2. Get Start  

<a id="anchor5"></a>  

**2.1 Dependences**  
The library depends only on:  
- [PHP](https://www.php.net/) (>= 8)   
- [Composer](https://getcomposer.org/)  
- Database ([MariaDB](https://mariadb.org/) or [MySQL](https://www.mysql.com/) or [Postgres](https://www.postgresql.org/)(comming soon) or [SQL-Server](https://www.microsoft.com/pt-br/sql-server/sql-server-2022)(comming soon))

<a id="anchor6"></a>

**2.2 Instalation**  
Run the following command:  
```bash
composer require jotagp/multi-upsert  
```

<a id="anchor7"></a>

**2.3 Methods**  
There are three possible methods:  

`push($associative_array)`  
This method works to include new values ​​in the insert. Note that the expected argument is an associative array, where the index of this array must always refer to the attribute of the table in question. Attributes that you do not specify will be included with their respective default values.  

`exec()`  
This method partitions your insert into N multiple inserts,  then run, always taking into account the amount allowed in a transaction by your database instance (max_allowed_package) . That is, in an insert of 100 thousand records, hypothetically speaking the function will create 10 multiple inserts, each with 10 thousand records.  

`config($associative_array)`  
This method allows you to edit some behavior of the object. Possible configurations (so far) are:
- update_if_exists: updates a record if a corresponding key already exists.
  - fields_to_update: inside update_if_exists, you can specify which fields should be updated.
  - concat_new_values: allows concatenating new values ​​to existing ones.
  - skip_if_already_exists: preserves the value that already exists, ignoring the new one.
  - skip_if_new_is_empty: preserves the value that already exists, in case the new one is empty or null.
- insert_multiple: if false, insert records one row per time.

<a id="anchor8"></a>
## 3. Usage

<a id="anchor9"></a>

**3.1 MariaDB / MySQL**

```php
// code like
require 'vendor/autoload.php';
use jotagp\multi_upsert\mysql as upsert;

$connection = new mysqli('host', 'user', 'pass', 'db', 'port', 'socket');

// insert data
$insert = new upsert($connection, 'persons');
foreach ($persons as $person) {

  $insert->push($person);

}
$insert->exec();

// update data (in this case, just if fields nick and mail are empty)
$update = new upsert($connection, 'persons');
$update->config([
  'update_if_exists' => [
    'fields_to_update' => [
      'nick',
      'mail'
    ],
    'concat_new_values' => false,
    'skip_if_new_is_empty' => false
    'skip_if_already_exists' => true,
  ]
]);
foreach ($persons as $person) {

  $update->push($person);

}
$update->exec();
```
```sql
-- sql like
INSERT INTO persons (id, name, nick, mail) VALUES (1, 'João', 'Jota', 'joao@mail.com'), 
                                                  (2, 'Maria', 'Mari', 'maria@mail.com'),
                                                  [...]
ON DUPLICATE KEY UPDATE nick = IF(nick IS NOT NULL AND TRIM(nick) NOT LIKE '', nick, VALUES(nick)),
                        mail = IF(mail IS NOT NULL AND TRIM(mail) NOT LIKE '', mail, VALUES(mail));
```

<a id="anchor10"></a>

**3.2 Postgres**   
(Comming soon)

<a id="anchor11"></a>

**3.3 SQL-Server**   
(Comming soon)

<a id="anchor12"></a>
## 3. Enjoy
Thanks for making it this far. I hope that this package will help you to have more performance in your day.   
I remain at your disposal if you need assistance or have any suggestions. 
You can contact me on [Linkedin](https://www.linkedin.com/in/jo%C3%A3o-gabriel-pereira-909085105/).