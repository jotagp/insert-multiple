# INSERT-MULTIPLE

## Sumary
1. Introduction  
	1.1 What is  
	1.2 Why  
	1.3 Licence  
2. Get Started  
	2.1 Dependences  
	2.2 Instalation  
	2.3 Usage  
	2.4 Methods  
	2.5 Exemple  
3. Enjoy  

## 1. Introduction  

**1.1 What is**  
This is a simple PHP package to insert multiple data into a MySQL/MariaDB database.  

**1.2 Why**  
This package was created with the aim of reducing data insertion time. To understand the magnitude of the problem, let's assume that we want to go through the items in a list and insert them into a database.  
```php
// example with insert tradicional
foreach ($list as $item) {
	$insert = "INSERT INTO `table1`(`numbers`, `description`) VALUES ({$item['number']}, {$item['description']});
	$connection->query($insert) or die ($connection->error);
}
```
The example above works. However, when working with a large volume of data, traditional insertion is not a viable option. This is because it inserts a single record at a time. So, if you have 100,000 records, there will be 100,000 insertions and, consequently, 100,000 trips to the hard disk to persist this data, and this task will take a long time (of course, it depends on the size of your data).
One "option" is to control the transaction from the database manually, something like:
```php
// example cwith insert tradicional and transaction control
$connection->begin_transaction();
foreach ($list as $item) {
	$insert = "INSERT INTO `table1`(`numbers`, `description`) VALUES ({$item['number']}, {$item['description']});
	$connection->query($insert) or die ($connection->error);
}
$connection->commit();
```
However, this approach still does not definitively solve our problem, as the time gain is not significant. What to do then? Simple! make a multiple insert:
```php
// example with manually insert multiple
$connection->begin_transaction();
$insert = "INSERT INTO `table1`(`numbers`, `description`) VALUES ";
foreach ($list as $item) {
	$insert .= "({$item['number']}, {$item['description']}) "; // concat new values 
}
$insert = str_replace(") (", "), (", $insert); // separe values
$connection->query($insert) or die ($connection->error);
$connection->commit();
```
However (there's always a however, right?), there is a transaction limit allowed by the bank, and this limit is easily reached when a very extensive query is set up.
And that, my friends, is where this package comes in. It will partition your values ​​into N Multiple slots, according to your bank's capacity:
```php
// example with package
$insert = new insert_multiple($connection, "table1");
foreach ($list as $item) {
	$insert->push($item); // concat new values
}
$insert->exec(); // run inserts
```
And it's that simple.


**1.3 License**  
This code is licensed under the [MIT license](https://opensource.org/licenses/MIT).  


## 2. Get Start  

**2.1 Dependences**  
The library depends only on:  
- [PHP](https://www.php.net/)   
- [Composer](https://getcomposer.org/)  
- [MariaDB](https://mariadb.org/) or [MySQL](https://www.mysql.com/)  


**2.2 Instalation**  
Run the following command:  
```bash
composer require jotagp/insert-multiple  
```

**2.3 Usage**  
Include the dependences in your PHP project:  
```php
require 'vendor/autoload.php';
use jotagp\insert_multiple\insert_multiple;
$connection = new mysqli('host', 'user', 'pass', 'database');
$insert_multiple = new insert_multiple($connection, 'table-name');
```
**2.4 Methods**  
There are three possible methods:  

`push($associative_array)`  
This method works to include new values ​​in the insert. Note that the expected argument is an associative array, where the index of this array must always refer to the attribute of the table in question. Attributes that you do not specify will be included with their respective default values.  

`exec()`  
This method partitions your insert into N multiple inserts,  then run, always taking into account the amount allowed in a transaction by your database instance (max_allowed_package) . That is, in an insert of 100 thousand records, hypothetically speaking the function will create 10 multiple inserts, each with 10 thousand records.  

`config($associative_array)`  
This method allows you to edit some behavior of the object. It can come in handy when you run into an error. By changing some properties it will be easier to understand the problem. 
The possible configurations (so far) are:
- insert_multiple (bool): switch to traditional insert. By default, is always true;
- debug (bool): print what step is happening. By default, is always false;  

**2.5 Example**  
Inserting 100 thousand random data into database.  Consider the following data structure:  
```sql
DROP DATABASE IF EXISTS `0temp`;
CREATE DATABASE `0temp`;
USE `0temp`;

DROP TABLE IF EXISTS `table1`;
CREATE TABLE `table1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` int(11) DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```
Then:  
```php
<?php

require  'vendor/autoload.php';
use  jotagp\insert_multiple\insert_multiple;

$connection = new mysqli('localhost','root', 'root', '0temp') or die($connection->error);
$insert_multiple = new  insert_multiple($connection, 'table1');

// generate randon numbers, between 1 and 1000
$count =  0;
while ($count < 100000) {

	$list[] = ['value' => rand(1, 1000)];
	$count +=  1;
	
}

// insert multiple
echo  "\nStart at: ".  Date('Y-m-d H:i:s') .  "\n[ ";
$count =  0; // for print a simple log
// $insert_multiple->config([
// 	'insert_multiple' => false,
// 	'debug' => true
// ]);
foreach ($list as $item) {

	$table1 = []; // clean the associative array
	$table1['number'] = $item['value']; // the 'number' index, matches the database attribute

	// magic is here
	$insert_multiple->push($table1); // push the associative array 

	$count += 1;
	if ($count % 1000 ==  0) echo "#";
	if ($count % 10000 ==  0) echo " ". $count ." ";
	
}
// magic is here two
$insert_multiple->exec();

echo  "]\nfinished at: ".  Date('Y-m-d H:i:s') .  "\n";

?>
```


## 3. Enjoy
Thanks for making it this far. I hope that this package will help you to have more performance in your day.   
I remain at your disposal if you need assistance or have any suggestions. 
You can contact me on [Linkedin](https://www.linkedin.com/in/jo%C3%A3o-gabriel-pereira-909085105/).