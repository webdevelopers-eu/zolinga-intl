# Language Selector Web Component

Custom element that renders an inline language switcher with popup.

## Usage

```html
<language-selector
    data-curr-locale="cs-CZ"
    data-curr-name="čeština"
    data-curr-name-en="Czech">
    <div class="language-popup" popover="auto">
        <a class="language current" href="/cs/..." data-locale="cs-CZ" data-name="čeština" data-name-en="Czech">
            <span>čeština</span>
        </a>
        ...
    </div>
</language-selector>
```

## Behavior

- Displays a globe icon (`::before`) and current language name (`::after`) inline
- Click opens a popover with all language options
- Language items are `<a>` elements — native navigation, no JS needed
- Click outside closes the popup

## CSS Customization

The component uses CSS anchor positioning. Override these selectors:

```css
language-selector { /* inline trigger */ }
language-selector::before { /* globe icon */ }
language-selector::after { /* language name */ }
.language-popup { /* popover box */ }
.language-popup .language { /* menu links (<a>) */ }
.language-popup .language:hover { /* hover state */ }
.language-popup .language.current { /* active language */ }
```
