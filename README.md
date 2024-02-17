# A PHP implementation for using the Compact Message Format
This is an attempt at implementing a PHP port for [The Compact Message Format bindings project](https://github.com/bitcoinclassic/cmf-bindings).

In creating this port, we referenced the Java and C# implementations the most, but the tests have been influenced by all 
the different implementations.

# What is the Compact Message Format?
As noted in the README of the original project:
> Bitcoin Classic introduced the Compact Message Format as a very simple but powerful format to encode and decode any 
> type of messages.
>
> The compact message format is a key/value-pair based format. Each key/value pair is called a token and a message is 
> built up by a series of tokens.

# Implementation Details
While the other implementations require a byte array to be given to the `MessageBuilder` and `MessageParser`, PHP does
not have a concept of that. Instead, the classes in this package will accept either an array (specifically a list) or a
stream resource. 

For convenience, when using the `addByteArray` method on the `MessageBuilder`, this implementation will allow the items
in the given array to either be byte values or individual ASCII characters (or a combination of these). When given an
ASCII character, the package will convert that character to its corresponding byte value before adding it to the
message.

Similarly, when the `MessageParser` is given an array for its data source (as opposed to a stream resource), that array
can contain either byte values or individual ASCII characters. No matter the input, though, calling `getByteArray` on
the parser will *always* return an array of byte values only.

Because PHP does not have support for method overloading, the `MessageBuilder` has explicitly named `add...` methods
for each of the types that can be added. These should correspond with explicitly named `get...` methods defined on the
`MessageParser`.

Unlike other implementations, there is no explicit `close()` method to be called.

When reading bytes from a stream, the position parameter is ignored. Instead, the package will always read the next byte
(or bytes) from the stream.

# Using the Package
To demonstrate usage of the package, we will use the same short example from the original project: a message with 3
tokens, each having a name and a value.
<pre>
   Name=Paris
   Population=2229621
   Area=105.6
</pre>

## Message Creation
For creation of messages we use the [builder pattern](https://en.wikipedia.org/wiki/Builder_pattern) in the form of the
`MessageBuilder` class.

The `MessageBuilder` class has a series of `add...()` methods each of which appends a token to your message. Each value that 
is added to a message requires a tag, with a tag being an integer between 0 and 65535 (inclusively).

```php
$bytes = [];
$builder = new MessageBuilder($bytes, 0);
$builder->addString(CITY_NAME_TAG, 'Paris');
$builder->addInt(CITY_POPULATION_TAG, 2229621);
$builder->addDouble(CITY_AREA_TAG, 105.6);
```

## Message Parsing
The MessageParser is using more of a SOX parser approach where you call `MessageParser.Next()` and then you can ask the 
parser for the tag and the actual value.

```php
$parser = new MessageParser($inputStream, 0, $inputStreamLength);
while ($parser->next() == State.FoundTag) {
    if ($parser->tag() == CITY_POPULATION_TAG) {
        $population = $parser->getInt();
        break;
    }
}
```
## Exception Handling
### `\InvalidArgumentException`
When there are problems with the data being passed into a method, the package will throw this exception. This could
include being passed a non-list array when one is expected, or being passed a negative number when one is not allowed.
Note, though, that this does not include errors with the underlying data being processed: those will throw either a
`SerializationException` or an `UnserializationException`.

_An \InvalidArgumentException indicates an issue that should be resolved by the calling code._

### `SerializationException`, `UnserializationException`
These are thrown when the actual data being processed has errors. The resolution of one of these exceptions depends on
the original source of the data.

### `InternalPackageException`
These are thrown when something unexpected has happened with the package's own code.

_These are exceptions that should be reported, as they need to be resolved by the package._
