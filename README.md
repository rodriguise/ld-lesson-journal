# Lesson Journal for LearnDash

A journaling companion for LearnDash LMS. Course designers define journal prompts that students respond to within lessons and topics. Entries persist, are editable, and can be reviewed or exported as PDF at the end of a course.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- LearnDash LMS 4.0+

## Installation

1. Download the latest release zip from [GitHub Releases](https://github.com/rodriguise/ld-lesson-journal/releases)
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. LearnDash must be active — the plugin shows a notice if it's missing

## Features

- **Journal Prompts** — Custom post type with rich text support via the block editor
- **Gutenberg Blocks** — `Prompt Group` and `Prompt` blocks for the lesson editor, plus a `Journal View` block for displaying entries
- **Shortcode Fallback** — `[ldj_group]` / `[ldj]` / `[ldj_journal]` for classic editor users
- **Completion Gating** — Optionally require journal entries before a lesson can be marked complete
- **PDF Export** — Students can download their journal as a PDF
- **Print Support** — Clean print layout with site logo, course title, and student name
- **Admin Entries Page** — Filterable list of all student entries under the LearnDash menu

## Gutenberg Blocks

### Prompt Group

Parent block that wraps one or more Prompt blocks. Found under the **LearnDash LMS Blocks** category.

| Setting | Description |
|---------|-------------|
| Required | When enabled, all prompts in the group must be completed before the student can mark the lesson complete |
| Heading | Optional heading displayed above the prompt group |

### Prompt

Child block (must be inside a Prompt Group). Each block references a journal prompt post.

- **Select existing** — Search and pick from published prompts
- **Create inline** — Quick-create a prompt with plain text directly in the editor
- **Full Editor** — Opens the prompt's CPT edit screen for rich formatting (headings, lists, bold, etc.)
- **Refresh** — Reloads the prompt content after editing in the full editor

### Journal View

Displays a student's journal entries for a course. Place this on any page or lesson.

| Setting | Description |
|---------|-------------|
| Course | The course to pull entries from (required) |
| Lesson | Filter to a specific lesson (optional — shows all lessons if blank) |
| Show course title | Display the course title on screen (default: off) |
| Show student name | Display the student's name on screen (default: off) |
| Show print button | Show the print icon button (default: on) |
| Heading | Optional heading above the journal view |

## Shortcodes

### `[ldj_group]`

Wraps one or more `[ldj]` shortcodes. Must be used on a LearnDash lesson or topic.

```
[ldj_group required="1" heading="Reflection Questions"]
  [ldj id="123"]
  [ldj id="456"]
[/ldj_group]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `required` | `0` | Require all entries for lesson completion |
| `heading` | | Optional heading text |

### `[ldj]`

Renders a single prompt's textarea. Must be inside `[ldj_group]`.

```
[ldj id="123"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `id` | | The journal prompt post ID (required) |

### `[ldj_journal]`

Renders the full journal view with pagination, print, and PDF export.

```
[ldj_journal course_id="10" show_title="1" show_print="1" heading="My Journal"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `course_id` | | Course ID (required) |
| `lesson_id` | `0` | Filter to a specific lesson |
| `show_title` | `0` | Show course title on screen |
| `show_student` | `0` | Show student name on screen |
| `show_print` | `1` | Show print button |
| `heading` | | Optional heading text |

## Journal Prompts (CPT)

Create and manage prompts under **LearnDash LMS → Journal Prompts**.

- **Title** — For admin identification (shown in block picker, not rendered to students)
- **Content** — The question text displayed to students (supports full block editor formatting)
- **Categories** — Organize prompts with the Journal Prompt Category taxonomy

### Prompt Settings (meta box)

| Field | Default | Description |
|-------|---------|-------------|
| Textarea Rows | 5 | Number of rows for the student textarea |
| Placeholder | | Placeholder text shown in the empty textarea |
| Hint Text | | Guidance text displayed below the prompt in smaller font |
| Max Characters | 0 | Character limit (0 = unlimited) |

## Completion Gating

When a Prompt Group has **Required** enabled:

1. The LearnDash "Mark Complete" button is disabled until all prompts in the group have entries
2. A message appears: *"Complete all journal entries to continue."*
3. Server-side validation prevents bypassing via form manipulation
4. Once entries are saved, the button enables automatically
5. If a student deletes an entry after completing, the gate re-activates

## Admin Entries

View all student entries under **LearnDash LMS → Journal Entries**.

- Filter by lesson, prompt, or student
- Sort by any column
- View full entry text via modal
- Bulk delete (requires `edit_others_posts` capability)

## PDF Export & Print

The Journal View includes two action buttons:

- **Save (disk icon)** — Generates and downloads a PDF of the full journal
- **Print (printer icon)** — Opens the browser print dialog with a clean layout

Both include the site logo, course title, and student name in the header. The print layout hides all navigation and UI chrome.

## Development

### Prerequisites

- Node.js 20+
- Docker (for wp-env)

### Setup

```bash
git clone https://github.com/rodriguise/ld-lesson-journal.git
cd ld-lesson-journal
npm install
```

### Commands

```bash
npm run build        # Build block JS/CSS
npm run start        # Watch mode for development
npm run env:start    # Start local WordPress via wp-env
npm run env:stop     # Stop wp-env
```

### Project Structure

```
src/
  lesson-journal.php              # Plugin bootstrap
  uninstall.php                   # Clean removal
  includes/
    class-ldj-db.php              # Database table
    class-ldj-post-type.php       # CPT + taxonomy
    class-ldj-entry.php           # Entry CRUD
    class-ldj-ajax.php            # AJAX handlers
    class-ldj-shortcode.php       # [ldj_group] / [ldj]
    class-ldj-journal-shortcode.php  # [ldj_journal]
    class-ldj-completion.php      # LearnDash gating
    class-ldj-admin-entries.php   # Admin list table
  blocks/
    prompt-group/                 # Parent block
    prompt/                       # Child block
    journal-view/                 # Journal display block
  assets/
    css/ldj-frontend.css          # Frontend styles + print
    css/ldj-admin.css             # Admin styles
    js/ldj-frontend.js            # Frontend interactions
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.
