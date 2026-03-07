# LazyMan Tools - Design System

## 1. Brand Identity

### Logo
- **Style**: Minimalist, monochrome
- **Elements**: Checkmark + Lazy sloth silhouette
- **Variations**: Dark on light, Light on dark

### Color Palette

```css
:root {
    /* Primary - Monochrome */
    --white: #FFFFFF;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;
    --black: #000000;
    
    /* Semantic (subtle use only) */
    --success: #10B981;
    --warning: #F59E0B;
    --error: #EF4444;
    --info: #3B82F6;
}
```

---

## 2. Typography

### Font Family
```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

body { font-family: 'Inter', system-ui, sans-serif; }
```

### Type Scale
| Class | Size | Weight | Use |
|-------|------|--------|-----|
| `.text-display` | 3rem | 700 | Hero text |
| `.text-h1` | 2.25rem | 600 | Page titles |
| `.text-h2` | 1.875rem | 600 | Section headers |
| `.text-h3` | 1.5rem | 600 | Card titles |
| `.text-body` | 1rem | 400 | Body text |
| `.text-small` | 0.875rem | 400 | Secondary text |
| `.text-caption` | 0.75rem | 400 | Labels, hints |

---

## 3. Components

### Buttons
```html
<!-- Primary Button -->
<button class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition">
    Primary
</button>

<!-- Secondary Button -->
<button class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
    Secondary
</button>

<!-- Ghost Button -->
<button class="px-4 py-2 text-gray-600 hover:text-black hover:bg-gray-100 rounded-lg transition">
    Ghost
</button>
```

### Cards
```html
<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
    <h3 class="font-semibold text-gray-900">Card Title</h3>
    <p class="text-gray-600 mt-2">Card content</p>
</div>
```

### Inputs
```html
<input type="text" 
    class="w-full px-4 py-2 border border-gray-300 rounded-lg 
           focus:ring-2 focus:ring-black focus:border-transparent 
           outline-none transition"
    placeholder="Enter text...">
```

### Task Card
```html
<div class="bg-white border border-gray-200 rounded-lg p-4 cursor-grab">
    <div class="flex items-start gap-3">
        <input type="checkbox" class="w-5 h-5 mt-0.5 rounded border-gray-300">
        <div class="flex-1">
            <h4 class="font-medium text-gray-900">Task Title</h4>
            <p class="text-sm text-gray-500 mt-1">Due Dec 31</p>
        </div>
        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">HIGH</span>
    </div>
</div>
```

---

## 4. Layout

### Sidebar Navigation
```
Width: 256px (16rem)
Background: White
Border: Right 1px gray-200
```

### Main Content
```
Padding: 24px
Background: gray-50
```

### Responsive Breakpoints
| Breakpoint | Width |
|------------|-------|
| Mobile | < 640px |
| Tablet | 640px - 1024px |
| Desktop | > 1024px |

---

## 5. Icons
Using Heroicons (outline style for consistency):
- Dashboard: `squares-2x2`
- Tasks: `check-circle`
- Projects: `folder`
- Clients: `users`
- Invoices: `document-text`
- Finance: `currency-dollar`
- Inventory: `cube`
- Timer: `clock`
- AI: `sparkles`
- Settings: `cog-6-tooth`

---

## 6. Animations

```css
/* Transitions */
.transition-default {
    transition: all 0.2s ease;
}

/* Hover lift */
.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Fade in */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
```

---

*Design System v1.0.0*
