# CakePHP-AppDecorator
A decorator for CakePHP 2.x and PHP 5.3+

## Setup
1. Copy AppDecorator.php to APP_DIR/Decorator/AppDecorator.php
2. Modify bootstrap.php to include the following:
  
  ```
  App::build(array(
      'Decorator' => array('/app/Decorator/')
  ), App::REGISTER);
  ```
  This will add the search path for Cake when you need to instantiate a decorator.
  
## Example Usage

### Schema
The `user` table contains the following:

| field | type    |
|-------|---------|
| id    | INT     |
| fname | VARCHAR |
| lname | VARCHAR |


### Decorator
```php
class UserDecorator extends AppDecorator {
    function name() {
        return $this->fname . " " . $this->lname;
    }
    
    /**
     * For illustration purposes $this->fname (and $this->lname, similarly)
     * does the following internally... You don't need to define any of the
     * attributes that are available from the passed data array that you
     * instantiate the decorator with.
     */
    function fname() {
        return $this->attributes['fname'];
    }
}

```

### Controller
```php
$this->loadModel('User');
$data = $this->User->findById(1);

App::uses('UserDecorator', 'Decorator');
$user = new UserDecorator($data);
```

### View
```html
<div>First name: <?= $user->fname ?></div>
<div>Last name: <?= $user->lname ?></div>
<div>Full name: <?= $user->name ?></div>
```
