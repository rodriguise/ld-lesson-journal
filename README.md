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
- **Required Prompts** — Per-prompt required toggle with minimum character enforcement
- **Course Journal Page** — Virtual page at `/course-journal/` with lesson filtering and per-lesson pagination
- **PDF Export** — Students can download their journal as a PDF
- **Print Support** — Clean print layout with site logo, course/lesson title, and student name
- **Admin Entries Page** — Filterable list of all student entries under the LearnDash menu
- **Inline Prompt Settings** — Edit prompt settings (rows, placeholder, required, min/max chars) directly from the block sidebar without leaving the page

## Gutenberg Blocks

### Prompt Group

Parent block that wraps one or more Prompt blocks. Found under the **LearnDash LMS Blocks** category.

| Setting | Description |
|---------|-------------|
| Required for lesson completion | All prompts must be completed before the student can mark the lesson complete |
| Show View Journal button | Show/hide the "View Journal" link that opens the course journal page (default: on) |
| Prompts per page | Paginate prompts within the group (0 = show all) |
| Heading | Optional heading displayed above the prompt group |
| Instructions | Optional instructions text below the heading |

### Prompt

Child block (must be inside a Prompt Group). Each block references a journal prompt post.

- **Select existing** — Search and pick from published prompts
- **Create inline** — Quick-create a prompt with plain text directly in the editor (includes required, min/max chars)
- **Full Editor** — Opens the prompt's CPT edit screen for rich formatting (headings, lists, bold, etc.)
- **Refresh** — Reloads the prompt content after editing in the full editor
- **Sidebar Settings** — Edit the prompt's settings (rows, placeholder, required, min/max chars) directly from the block sidebar without leaving the page. Changes are saved to the prompt post via the REST API.

### Journal View

Displays a student's journal entries for a course. Place this on any page or lesson.

| Setting | Description |
|---------|-------------|
| Course | The course to pull entries from (required) |
| Lesson | Filter to a specific lesson (optional — shows all lessons if blank) |
| Show course title | Display the course title on screen (default: off) |
| Show student name | Display the student's name on screen (default: off) |
| Show print button | Show the print icon button (default: on) |
| Show download button | Show the PDF download button (default: on) |
| Show refresh button | Show the refresh button (default: on) |
| Show lesson filter | Show a dropdown to filter entries by lesson (default: off) |
| Show content inline | Show journal entries on screen (default: on). When off, only the toolbar is shown. |
| Button style | Icons (compact) or Text labels |
| Heading | Optional heading above the journal view |
| Instructions | Optional instructions text below the heading |

## Shortcodes

### `[ldj_group]`

Wraps one or more `[ldj]` shortcodes. Must be used on a LearnDash lesson or topic.

```
[ldj_group required="1" heading="Reflection Questions" instructions="Take your time." per_page="2"]
  [ldj id="123"]
  [ldj id="456"]
[/ldj_group]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `required` | `0` | Require all entries for lesson completion |
| `heading` | | Optional heading text |
| `instructions` | | Optional instructions text |
| `per_page` | `0` | Prompts per page (0 = show all) |

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
[ldj_journal course_id="10" show_title="1" show_print="1" show_filter="1" heading="My Journal"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `course_id` | | Course ID (required) |
| `lesson_id` | `0` | Filter to a specific lesson |
| `show_title` | `0` | Show course title on screen |
| `show_student` | `0` | Show student name on screen |
| `show_print` | `1` | Show print button |
| `show_save` | `1` | Show PDF download button |
| `show_refresh` | `1` | Show refresh button |
| `show_filter` | `0` | Show lesson filter dropdown |
| `show_content` | `1` | Show entries inline (0 = toolbar only) |
| `button_style` | `icons` | `icons` or `text` |
| `heading` | | Optional heading text |
| `instructions` | | Optional instructions text |

## Journal Prompts (CPT)

Create and manage prompts under **LearnDash LMS → Journal Prompts**.

- **Title** — For admin identification (shown in block picker, not rendered to students)
- **Content** — The question text displayed to students (supports full block editor formatting)
- **Categories** — Organize prompts with the Journal Prompt Category taxonomy

### Prompt Settings (meta box)

| Field | Default | Description |
|-------|---------|-------------|
| Number of lines | 5 | Number of rows for the student textarea |
| Placeholder | | Placeholder text shown in the empty textarea |
| Required | off | When enabled, the student must write at least the minimum characters |
| Min Characters | 1 | Minimum characters required (only shown when Required is on) |
| Max Characters | 0 | Character limit (0 = unlimited) |

These settings can also be edited directly from the block sidebar when a Prompt block is selected — no need to open the prompt CPT edit screen.

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

## Course Journal Page

The plugin registers a virtual page at `/course-journal/` that provides a dedicated full-page journal view for students.

- Automatically accessible via the **View Journal** button in prompt groups
- URL format: `/course-journal/?course_id=123`
- Includes a lesson filter dropdown for navigating between lessons
- Paginates by lesson when "All Lessons" is selected; shows all entries when a specific lesson is filtered
- Topic entries display their parent lesson in the section header (e.g., "Lesson 1 | Topic 1")
- Redirects to the login page if the user is not authenticated

After first activation, visit **Settings → Permalinks → Save Changes** to flush rewrite rules.

## PDF Export & Print

The Journal View includes action buttons (configurable per instance):

- **Download** — Generates and downloads a PDF of the full journal
- **Print** — Opens the browser print dialog with a clean layout
- **Refresh** — Reloads entries via AJAX

Both PDF and print include the site logo and a header formatted as "Site Name | Course Name | Lesson Name" along with the student name. The print layout hides all navigation and UI chrome.

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
    class-ldj-journal-page.php    # Virtual /course-journal/ page
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
