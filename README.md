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
For all the following example we assume that the `user` table contains only the following structure:

| field | type    |
|-------|---------|
| id    | INT     |
| fname | VARCHAR |
| lname | VARCHAR |

### Controller
```php
$this->loadModel('User');
$data = $this->User->findById(1);

App::uses('UserDecorator', 'Decorator');
$user = new UserDecorator($data);

$this->set('user', $user);
```

### View
```html+php
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
     * the following internally:
     *
     * function fname() {
     *     return $this->attributes['fname'];
     * }
     *
     * You do not need to define getters for any of the attributes that are
     * available from the passed data array that you instantiate the decorator
     * with.
     */
}
```

## JSON Serializer
Using the decorator object is very useful if you are working with AJAX or API responses as it allows you to be selective about which attributes (or methods) you wish to expose.

### Simple serializer example
By default, the decorator will serialize all available attributes (note that methods are not automatically added).

```php
# in a action function in UserController.php
$user = $this->User->findById($id);
$user = new UserDecorator($user);

$this->set('user', $user->jsonSerialize());
$this->set('_serialize', array('user'));
```

### Serializing methods or attributes selectively

1. Pass an array of attributes or methods to serialize as the first argument to `jsonSerialize()`:

  ```php
  $user = new UserDecorator($user);
  $this->set('user', $user->jsonSerialize(array('name')));
  $this->set('_serialize', array('user'));
  ```

2. Set the `$serializableAttributes` property in the decorator:
  ```php
  class UserDecorator extends AppDecorator {
      protected $serializableAttributes = array('name');
  # ...
  ```

3. Disable serializing altogether:

  Set the `$serializableAttributes` to `false`. (Note: an empty array or `null` will serialize all attributes instead.)

  ```php
  class UserDecorator extends AppDecorator {
      protected $serializableAttributes = false;
  # ...
  ```

## Integrate with the `Model::find()` method (optional)

### Setup
Add the following to your `APP_DIR/Model/AppModel.php` file:

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
}
```

### Usage

```php
$this->loadModel('User');
$user = $this->User->find('decorateFirst', array('conditions' => array('id' => 1)));
#=> Will return a single decorated user who has an id = 1

$users = $this->User->find('decorate', array('conditions' => array('id <=' => 10)));
#=> Will return an array of decorated users who have an id <= 10
```

Note that you can use `decorateFirst` even when your query has the possibility to return multiple rows.
`decorateFirst` actually adds a `LIMIT 1` to your query.
