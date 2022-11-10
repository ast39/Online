# Online - Users online accounting for Laravel Framework

The library makes it possible to control users online, accounting new users, 
monitor inactivity time, and automatically clean up inactive users.

## Installation

Install the latest version with

```bash
$ composer require ast/online
```

## Basic Usage

```php
<?php

use Ast\Online\Online;

// Get instance
Online::instance()->;

// Set session ID
->setSessionId();

// Set downtime for change status to inactive
->setDownTime(int $seconds);

// Set path to storage file (path is specified from the root 'storage/app/' - for example: online/users.txt)
->setLogFile(string $path);
```

## Main Functionality

```php
<?php

// Auto cleaning inactive users
->autoClean(): bool;

// accounting user activity
->logVisit(): bool;

// Get counter of online users
->count(): int;

// Remove user from online sector
->drop(): bool;

// Check user for online status
->check(string 'session_id'): bool;
```

### Author

Alexandr Statut - <aleaxndr.statut@gmail.com>

### License

Online is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
