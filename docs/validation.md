# Validation

Attribute-based request validation. Define a DTO class, add validation attributes to properties, validate input data.

## Available validators

| Attribute | Parameters | Description |
|-----------|-----------|-------------|
| `#[Required]` | `message?` | Value must not be null or empty string |
| `#[StringLength]` | `min?, max?, message?` | String length bounds |
| `#[NumericRange]` | `min?, max?, message?` | Numeric value bounds |
| `#[Email]` | `message?` | Valid email address (filter_var) |
| `#[Regex]` | `pattern, message?` | Must match the regex pattern |
| `#[In]` | `values[], message?` | Must be one of the listed values |

**Scope fence:** exactly these 6 validators ship. No nested validation, no custom rules built in. Subclass or compose for additional validation.

## Usage

Define a DTO:

```php
use Karhu\Attributes\{Required, StringLength, Email, In};

final class CreateUserDto
{
    #[Required]
    #[StringLength(min: 3, max: 50)]
    public string $name = '';

    #[Required]
    #[Email]
    public string $email = '';

    #[In(values: ['admin', 'editor', 'viewer'])]
    public string $role = '';
}
```

Validate in a controller:

```php
$data = $request->body(); // auto-decoded JSON array
$errors = Validation::validate($data, CreateUserDto::class);

if ($errors !== []) {
    return (new Response(422))->json(['errors' => $errors], 422);
}
```

## Custom error messages

```php
#[Required(message: 'Please provide your name')]
#[StringLength(min: 3, message: 'Name is too short')]
public string $name = '';
```

## Optional fields

Fields without `#[Required]` are optional. When absent or empty, other validators are skipped (no false positives on missing optional data).
