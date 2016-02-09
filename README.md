# CakePHP-AppDecorator
A decorator pattern implementation for CakePHP 2.x and PHP 5.3+.  
Makes your views more pretty by encouraging code-reuse and cleaner syntax.

## Setup
1. Copy `AppDecorator.php` to `APP_DIR/Decorator/AppDecorator.php`
2. Modify `bootstrap.php` to include the following:
  
  ```php
  App::build(array(
      'Decorator' => array(DS . APP_DIR . DS . 'Decorator' . DS)
  ), App::REGISTER);
  ```
  This will add the search path for Cake when you need to instantiate a decorator.
  
3. Optionally, but highly recommended, auto-load the `AppDecorator` in `bootstrap.php` so that you don't need to require before the class definition of each decorator:
  ```
  App::uses('AppDecorator', 'Decorator');
  ```

`JsonSerializable` is a PHP 5.4 feature that allows us to _automatically_ let PHP know about how we wish to marshal our data into JSON. As such, in PHP 5.3, you will need to call `jsonSerialize()` yourself so that it returns a generic array instead of an object.

### PHP 5.4+
Modify the class declaration of `AppDecorator` to the following:
```php
<?php 
class AppDecorator implements IteratorAggregate, ArrayAccess, Countable, JsonSerializable {
# ...
```
### PHP 5.3
Leave the AppDecorator as is, but in every controller where you must return JSON, make sure to call `jsonSerialize()`. See below for an example.
  
## Basic Usage

### Schema
For all the following example we assume that the `users` table contains only the following structure:

| field | type    |
|-------|---------|
| id    | INT     |
| fname | VARCHAR |
| lname | VARCHAR |

### Controller
```php
<?php
class UsersController extends AppController {
	public $components = array('RequestHandler');
	
	# Note: Make sure you have this to your routes.php for these examples to work:
	# Router::mapResources('users');
    # Router::parseExtensions();
    
	function index() {
		App::uses('UserDecorator', 'Decorator');
		$users = $this->User->find('all');
		$this->set('users', new UserDecorator($users));
	}

	function view($id) {
		$data = $this->User->findById($id);

		App::uses('UserDecorator', 'Decorator');
		$user = new UserDecorator($data);

		$this->set('user', $user->jsonSerialize()); # With PHP 5.4+ you don't need to call
													# the jsonSerialize() method as long
                                                	# as you modified AppDecorator as
													# indicated in the Setup step
		$this->set('_serialize', array('user'));
	}
}
```

### Example JSON response
`GET /users/1.json`
```JSON
{
    "user": {
        "id": "1",
        "name": "Han Solo"
    }
}
```

### View (index.ctp)

```html+php
<table>
	<thead>
		<tr>
			<th>ID</th>
			<th>First</th>
			<th>Last</th>
			<th>Full</th>
		</tr>
	</thead>
	<tbody>
	<? foreach($users as $user): ?>
		<tr>
			<td><?= $user->fname ?></td>
			<td><?= $user->lname ?></td>
			<td><?= $user->name ?></td>
		</tr>
	<? endforeach ?>
	</tbody>
</table>
```

### Decorator
```php
class UserDecorator extends AppDecorator {
	public $serializableAttributes = array('id', 'name');

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

By default, the decorator will serialize all available attributes (note that methods are not automatically added). An example is already provided above in the context of returning a JSON API response.

### Serializing methods or attributes selectively

- Set the `$serializableAttributes` property in the decorator to act as the default when serializing:

  ```php
  class UserDecorator extends AppDecorator {
      public $serializableAttributes = array('id', 'name');
  # ...
  }
  ```
- Pass an array of attributes or methods to serialize as the first argument to `jsonSerialize()` (this overrides the class-defined default above):

  ```php
  $user = new UserDecorator($user);
  $this->set('user', $user->jsonSerialize(array('name')));
  $this->set('_serialize', array('user'));
  ```
  
- Disable serializing altogether (useful for associatied data):

  Set the `$serializableAttributes` to `false` or `array()`:

  ```php
  class UserDecorator extends AppDecorator {
      public $serializableAttributes = false;
  # ...
  }
  ```

## Returning a decorator instead of an array on Model::find()
Instead of having to require the use of a decorator and instantiating it, you can do so automatically when doing a `find()`.

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

#### `decorateFirst`
Decorate the first result like `Model::find('first')`:

```php
$user = $this->User->find('decorateFirst', array(
    'conditions' => array(
        'id' => 1
    )
));
```

```JSON
{
    "user": {
        "id": "1",
        "name": "Han Solo"
    }
}
```

#### `decorate`
Decorate all like `Model::find('all')`:

```php
$users = $this->User->find('decorate', array(
	'conditions' => array(
		'id <=' => 3
	)
));
```

```JSON
{
    "users": [
        {
            "id": "1",
            "fname": "Han",
            "lname": "Solo"
        },
        {
            "id": "2",
            "fname": "Luke",
            "lname": "Skywalker"
        },
        {
            "id": "3",
            "fname": "Princess",
            "lname": "Leia"
        }
    ]
}
```

Note that you can use `decorateFirst` even when your query has the possibility to return multiple rows.
`decorateFirst` actually adds a `LIMIT 1` to your query similar to the `first` find method.

  
## Associations
If your model contains any binds (hasOne, HABTM, hasMany), they can be decorated if they are nested into a parent record.

**This section is a work in progress.**
