Rollerworks MessageBusFormBundle
================================

The main purpose of this library is to handle the dispatching of Commands
through the Symfony Messenger, and mapping their exceptions to a Form structure.

The `MessageFormType` handles constructing of the Command message and 
dispatching the command during the submit phase of a form.

**Note:** With this system the Form becomes responsible for dispatching the Command, 
after submitting the command is handled and errors are mapped to the Form.

## Usage

... TBD.

## Requirements

PHP 7.2 and Symfony 4.2+.

## Installation

To install this package, add `rollerworks/messagebus-form-bundle` to your composer.json

```bash
$ php composer.phar require rollerworks/messagebus-form-bundle
```

Now, Composer will automatically download all required files, and install them
for you.

## Versioning

For transparency and insight into the release cycle, and for striving
to maintain backward compatibility, this package is maintained under
the Semantic Versioning guidelines as much as possible.

Releases will be numbered with the following format:

`<major>.<minor>.<patch>`

And constructed with the following guidelines:

* Breaking backward compatibility bumps the major (and resets the minor and patch)
* New additions without breaking backward compatibility bumps the minor (and resets the patch)
* Bug fixes and misc changes bumps the patch

For more information on SemVer, please visit <http://semver.org/>.

## Who is behind this library?

This library is brought to you by [Sebastiaan Stok](https://github.com/sstok).

## License

The package is released under the MIT License. See the bundled [LICENSE](LICENSE) file
for details.
