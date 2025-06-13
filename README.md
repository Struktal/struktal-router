# struktal-router

This is a PHP library that enables route handling in web applications.

# Installation

To install this library, include it in your project using Composer:

```bash
composer require struktal/struktal-router
```

# Usage

Before you can use this library, you need to customize a few parameters.
You can do this in the startup of your application:

```php
\struktal\Router\Router::setPagesDirectory("path/to/your/pages");
\struktal\Router\Router::setAppUrl("https://yourdomain.com");
\struktal\Router\Router::setAppBaseUri("/"); // Or if you want to use a subdirectory, e.g. "/your-app/"
\struktal\Router\Router::setStaticDirectoryUri("static/");
```

Then, you can use the library's features in your code.

## Defining routes

To define routes, you can use the `Router` class to register your routes.

A very simple example would be:
```php
\struktal\Router\Router::addRoute(
    "GET",
    "/",
    "index.php",
    "index"
);
```
This registers a route that responds to `GET` requests at the root URL (`/`) and serves the `index.php` file, which is expected to handle the request.
The `index.php` file must be located in the directory specified by `setPagesDirectory`.

You can also define routes with parameters:
```php
\struktal\Router\Router::addRoute(
    "GET",
    "/user/{i:userId}",
    "users/details.php",
    "user_details"
);
```
This registers a route that responds to `GET` requests at `/user/{i:userId}`, where `{i:userId}` is a placeholder for an integer parameter named `userId`.
The request will be handled by the `users/details.php` file, so the `details.php` file is now located in the subdirectory `users/` within the directory specified by `setPagesDirectory`.

A route can have multiple placeholders, all of them are denoted by curly braces with the data type and name of the placeholder inside.
The variable is then available in the `$_GET` superglobal array, so you can access it like this:
```php
$userId = $_GET['userId'];
```
The available data types for placeholders are:
- `b` for boolean values
- `f` for floating-point numbers
- `i` for integers
- `s` for strings

Finally, it is also possible to define routes that respond to multiple HTTP methods:
```php
\struktal\Router\Router::addRoute(
    "GET|POST"
    "/user",
    "user.php",
    "user"
);
```
In this case, the route will respond to both `GET` and `POST` requests at the `/user` URL, and the handling `user.php` script could serve an HTML form to create or update a user upon a `GET` request, and process the form submission upon a `POST` request.
However, this use-case is not recommended, as it is better to separate the handling of different HTTP methods into different routes for clarity and maintainability.

## Defining error routes

After defining your routes, you have to define error routes.
When stumbling upon a routing error, the users have to be redirected accordingly.
You can do this as follows:

```php
\struktal\Router\Router::setError400Route(\strukral\Router\Router::generate("400"));
\struktal\Router\Router::setError404Route(\struktal\Router\Router::generate("404"));
```

This will redirect the users to the existing routes named `400` and `404` to handle the respective errors.

## Starting the router

When you have followed the above steps, you can simply start the router in your application:

```php
$router = new \struktal\Router\Router();
$router->startRouter();
```

## More features

### Generate routes

You can generate URLs for your defined routes using the `generate` method of the `Router` class:

```php
$url = \struktal\Router\Router::generate("user_details", ["userId" => 42]);
```

This searches for the route named `user_details` and replaces the `{i:userId}` placeholder with the value `42`, resulting in a URL like `/user/42`.
You can pass an additional parameter `withHostUrl` as boolean to include the host URL in the generated output, resulting in a full URL like `https://yourdomain.com/user/42`.

### Get paths for static files

You can also get the paths for static files using the `getStaticFilePath` method:

```php
$staticFilePath = \struktal\Router\Router::staticFilePath("css/style.css");
```

# License

This software is licensed under the MIT license.
See the [LICENSE](LICENSE) file for more information.
