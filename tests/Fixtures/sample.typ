#set page(width: 10cm, height: auto)
#set text(font: "Latin Modern Roman", size: 11pt)

= Test Document

This is a sample Typst document for testing purposes.

== Section 1

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.

== Section 2

Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.

=== Subsection 2.1

Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.

#table(
  columns: 3,
  [*Name*], [*Age*], [*City*],
  [Alice], [25], [New York],
  [Bob], [30], [London],
  [Charlie], [35], [Tokyo]
)

== Code Example

```rust
fn main() {
    println!("Hello, Typst!");
}
```

== Math

The quadratic formula is:

$ x = (-b plus.minus sqrt(b^2 - 4a c)) / (2a) $

== Lists

1. First item
2. Second item
   - Nested item A
   - Nested item B
3. Third item

_End of document_