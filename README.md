# CakePHP-AppDecorator
A decorator pattern implementation for CakePHP 2.x and PHP 5.3+.  
Makes your views more pretty by encouraging code-reuse and cleaner syntax.

## Setup
1. Copy `AppDecorator.php` to `APP_DIR/Decorator/AppDecorator.php`
2. Modify `bootstrap.php` to include the following:
  
  ```php
  App::build(array(
      'Decorator' => array('/app/Decorator/')
  ), App::REGISTER);
  ```
  This will add the search path for Cake when you need to instantiate a decorator.
  
## Example Usage

### Schema
The `user` table contains the following (we assume nothing else is defined):
+-------+---------+
| field | type    |
+-------+---------+
| id    | INT     |
| fname | VARCHAR |
| lname | VARCHAR |
+-------+---------+

### Controller
```php
$this->loadModel('User');
$data = $this->User->findById(1);

App::uses('UserDecorator', 'Decorator');
$user = new UserDecorator($data);

$this->set('user', $user);
```

### View
```html
<div>First name: <?= $user->fname ?></div>
<div>Last name: <?= $user->lname ?></div>
<div>Full name: <?= $user->name ?></div>
```

### Decorator
```php
class UserDecorator extends AppDecorator {
    function name() {
        return $this->fname . " " . $this->lname;
    }
    
    /**
     * For illustration purposes $this->fname (and $this->lname, similarly) does
     * the following internally... You don't need to define getters for any the
     * attributes that are available from the passed data array that you
     * instantiate the decorator with.
     *
     * function fname() {
     *     return $this->attributes['fname'];
     * }
     */
}
```

## Integrate with the `Model::find()` method (Optional)

### Setup
Add the following to your AppModel:

```php
<?php
App::uses('Model', 'Model');
class AppModel extends Model {

    public $findMethods = array(
        'decorate' => true,
        'decorateFirst' => true
    );

    function decorate($data = array(), $class = null) {
        if ( ! $class) {
            $class = $this->name;
        }

        if (empty($data)) {
            if ($this->id) {
                $data = $this->read();
            } else {
                return array();
            }
        }

        $decoratorClass = "{$class}Decorator";
        App::uses($decoratorClass, 'Decorator');

        if (class_exists($decoratorClass)) {
            return new $decoratorClass($data);
        } else {
            return new AppDecorator($data, $class);
        }
    }

    function _findDecorate($state, $query, $results = array()) {
        if ($state === 'before') {
            return $query;
        }

        if (empty($results)) {
            return $results;
        }

        return $this->decorate($results);
    }

    function _findDecorateFirst($state, $query, $results = array()) {
        if ($state === 'before') {
            $query['limit'] = 1;
            return $query;
        }

        if (isset($results[0])) {
            if (empty($results[0])) {
                return $results;
            }

            return $this->decorate($results[0]);
        }

        if (empty($results)) {
            return $results;
        }

        return $this->decorate($results);
    }
```

### Usage

```php
$this->loadModel('User');
$user = $this->User->find('decorateFirst', array('conditions' => array('id' => 1)));
#=> Returns a single decorated user who has ID = 1
$users = $this->User->find('decorate', array('conditions' => array('id <=' => 10)));
#=> Returns an array of decorated users who have ID <= 10
