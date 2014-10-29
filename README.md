# blockspring.php

Php library to assist in creating and running blocks (cloud functions) with Blockspring.

### Installation

```shell
curl -o ./blockspring.php https://raw.githubusercontent.com/blockspring/blockspring-php/master/blockspring.php
```

### Example Usage

Save the following script to an example.php file:
```php
<?php

require('blockspring.php');

function hello_world($request, $response) {
  $my_sum = $request->params["num1"] + $request->params["num2"];

  $response->addOutput("sum", $my_sum);
  $response->end();
}

Blockspring::define(hello_world);

?>
```

Then in your command line write:
```shell
php example.php --num1=20 --num2=50
```

or

```shell
echo '{"num1":20, "num2": 50}' | php example.php
```

### License

MIT

### Contact

Email us: founders@blockspring.com