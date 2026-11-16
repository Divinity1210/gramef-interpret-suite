# Invoice Admin Design System Documentation

## Overview
This document outlines the comprehensive design system implemented for the Invoice Admin interface, ensuring consistent UI/UX patterns, accessibility compliance, and maintainable code structure.

## Color Palette

### Primary Colors
- **Primary Blue**: `#2196F3` (Material Design Blue 500)
- **Primary Blue Hover**: `#1976D2` (Material Design Blue 700)
- **Primary Blue Active**: `#0D47A1` (Material Design Blue 900)

### Secondary Colors
- **Success Green**: `#4CAF50` (Material Design Green 500)
- **Success Green Hover**: `#388E3C` (Material Design Green 700)
- **Warning Orange**: `#FF9800` (Material Design Orange 500)
- **Warning Orange Hover**: `#F57C00` (Material Design Orange 700)
- **Danger Red**: `#F44336` (Material Design Red 500)
- **Danger Red Hover**: `#D32F2F` (Material Design Red 700)
- **Info Cyan**: `#00BCD4` (Material Design Cyan 500)
- **Info Cyan Hover**: `#0097A7` (Material Design Cyan 700)

### Filter Button Colors
- **Filter Green**: `#2E7D32` (Material Design Green 800)
- **Filter Green Hover**: `#1B5E20` (Material Design Green 900)
- **Filter Green Active**: `#0D3C12` (Custom Dark Green)

### Neutral Colors
- **Secondary Gray**: `#6C757D` (Bootstrap Gray 600)
- **Secondary Gray Hover**: `#5A6268` (Bootstrap Gray 700)
- **Light Gray**: `#F8F9FA` (Bootstrap Light)
- **Dark Gray**: `#343A40` (Bootstrap Dark)

## Typography

### Font Specifications
- **Font Family**: System font stack (San Francisco, Segoe UI, Roboto, etc.)
- **Base Font Size**: 14px
- **Font Weight**: 500 (Medium)
- **Line Height**: 1.5

### Button Text
- **Font Size**: 14px
- **Font Weight**: 500
- **Text Transform**: None
- **Letter Spacing**: 0.02em

## Button System

### Standard Dimensions
- **Height**: 40px
- **Padding**: 0 16px (horizontal), calculated vertical
- **Border Radius**: 4px
- **Border**: 1px solid (color varies by type)

### Small Button Variant
- **Height**: 32px
- **Padding**: 0 12px
- **Font Size**: 13px

### Button States

#### Primary Buttons
- **Default**: Background `#2196F3`, Text `#FFFFFF`
- **Hover**: Background `#1976D2`, Transform `translateY(-1px)`
- **Active**: Background `#0D47A1`, Box-shadow `inset 0 2px 4px rgba(0,0,0,0.2)`
- **Focus**: Outline `2px solid #2196F3`, Offset `2px`

#### Success Buttons
- **Default**: Background `#4CAF50`, Text `#FFFFFF`
- **Hover**: Background `#388E3C`
- **Active**: Background `#2E7D32`

#### Info Buttons
- **Default**: Background `#00BCD4`, Text `#FFFFFF`
- **Hover**: Background `#0097A7`
- **Active**: Background `#00838F`

#### Warning Buttons
- **Default**: Background `#FF9800`, Text `#FFFFFF`
- **Hover**: Background `#F57C00`
- **Active**: Background `#E65100`

#### Danger Buttons
- **Default**: Background `#F44336`, Text `#FFFFFF`
- **Hover**: Background `#D32F2F`
- **Active**: Background `#B71C1C`

#### Secondary Buttons
- **Default**: Background `#6C757D`, Text `#FFFFFF`
- **Hover**: Background `#5A6268`
- **Active**: Background `#495057`

#### Filter Buttons
- **Default**: Background `#2E7D32`, Text `#FFFFFF`
- **Hover**: Background `#1B5E20`
- **Active**: Background `#0D3C12`

### Button Groups
- **Gap**: 4px between buttons
- **Alignment**: Flex start
- **Wrap**: No wrap for small groups

## Dropdown System

### Dropdown Toggle
- **Custom Implementation**: Uses `data-custom-dropdown` attribute
- **Bootstrap Override**: Disables default Bootstrap dropdown behavior
- **Animation Duration**: 300ms ease-in-out
- **Max Height**: 300px with scroll

### Dropdown Menu
- **Background**: `#FFFFFF`
- **Border**: 1px solid `rgba(0,0,0,0.15)`
- **Border Radius**: 4px
- **Box Shadow**: `0 4px 12px rgba(0,0,0,0.15)`
- **Z-Index**: 1050
- **Min Width**: 160px

### Dropdown Items
- **Padding**: 8px 16px
- **Font Size**: 14px
- **Hover Background**: `#F8F9FA`
- **Active Background**: `#E9ECEF`

## Animation & Transitions

### Standard Transitions
- **Duration**: 200ms ease-in-out
- **Properties**: background-color, border-color, color, box-shadow, transform

### Dropdown Animations
- **Duration**: 300ms ease-in-out
- **Transform**: translateY(-10px) to translateY(0)
- **Opacity**: 0 to 1

### Button Hover Effects
- **Transform**: translateY(-1px)
- **Box Shadow**: Enhanced elevation
- **Duration**: 200ms ease-in-out

## Accessibility Features

### Contrast Ratios
- **Minimum Ratio**: 4.5:1 (AA Compliance)
- **Enhanced Ratio**: 7:1 (AAA Compliance for critical elements)

### Focus Management
- **Focus Outline**: 2px solid primary color
- **Focus Offset**: 2px
- **Focus Visible**: Only on keyboard navigation

### Keyboard Navigation
- **Tab Order**: Logical flow through interactive elements
- **Escape Key**: Closes dropdowns and modals
- **Enter/Space**: Activates buttons and toggles
- **Arrow Keys**: Navigate dropdown items

### Screen Reader Support
- **ARIA Labels**: Descriptive labels for all interactive elements
- **ARIA Expanded**: Dynamic state for dropdown toggles
- **ARIA Hidden**: Decorative icons hidden from screen readers
- **Role Attributes**: Proper semantic roles

## Responsive Design

### Breakpoints
- **Mobile**: < 768px
- **Tablet**: 768px - 1024px
- **Desktop**: > 1024px

### Mobile Adaptations
- **Button Height**: Minimum 44px for touch targets
- **Dropdown Width**: Full width on mobile
- **Font Size**: Minimum 16px to prevent zoom

## Loading States

### Button Loading
- **Spinner**: CSS-only rotating icon
- **Disabled State**: Reduced opacity, no pointer events
- **Text Change**: "Loading..." or similar indicator

### Bulk Actions Loading
- **Progress Indicator**: Visual feedback during bulk operations
- **Disable Interactions**: Prevent multiple submissions
- **Success/Error States**: Clear feedback on completion

## Performance Considerations

### CSS Optimization
- **Custom Properties**: CSS variables for maintainable theming
- **Minimal Specificity**: Avoid deep nesting and !important
- **Efficient Selectors**: Class-based targeting

### JavaScript Optimization
- **Debouncing**: 300ms debounce for filter interactions
- **Event Delegation**: Efficient event handling for dynamic content
- **Memory Management**: Proper cleanup of event listeners

## Browser Support

### Minimum Requirements
- **Chrome**: 70+
- **Firefox**: 65+
- **Safari**: 12+
- **Edge**: 79+

### Progressive Enhancement
- **Fallbacks**: Graceful degradation for older browsers
- **Feature Detection**: Check for CSS/JS feature support
- **Polyfills**: Minimal polyfills for critical features

## Implementation Guidelines

### CSS Architecture
- **BEM Methodology**: Block, Element, Modifier naming
- **Component-Based**: Modular, reusable components
- **Utility Classes**: Common patterns as utilities

### JavaScript Patterns
- **Module Pattern**: Encapsulated functionality
- **Event-Driven**: Loose coupling between components
- **Error Handling**: Graceful error recovery

### Testing Checklist
- [ ] All buttons have consistent styling
- [ ] Dropdowns toggle properly
- [ ] Keyboard navigation works
- [ ] Screen reader compatibility
- [ ] Mobile responsiveness
- [ ] Loading states function
- [ ] Error handling works
- [ ] Performance is acceptable

## Maintenance

### Regular Updates
- **Color Palette**: Review and update quarterly
- **Accessibility**: Annual accessibility audit
- **Performance**: Monthly performance review
- **Browser Support**: Update based on analytics

### Documentation Updates
- **Change Log**: Document all design system changes
- **Version Control**: Semantic versioning for design system
- **Team Communication**: Share updates with development team

---

*Last Updated: October 2025*
*Version: 1.0.0*