# Course Board Pro

A lightweight, custom WordPress plugin that generates dynamic, filterable course directories. It isolates course data into specific "Projects" and builds automated frontend filters based on hierarchical tagging.

## Features
* **Project Isolation:** Run multiple distinct course boards on the same website without data crossover.
* **Dynamic Sidebar Filters:** Dropdown filters are generated automatically based on the tags assigned to courses in a specific project.
* **Semantic UI:** Parent tags dictate color coding for a consistent, professional frontend design.
* **Custom Meta Fields:** Built-in backend fields for Course Code, Duration, Explore Link, and a "New" badge.
* **Client-Side Search:** Lightning-fast, instant filtering by course title and course code without page reloads.

---

## 🚀 Installation

1. Download or clone this repository into a `.zip` file.
2. Log in to your WordPress Admin dashboard.
3. Navigate to **Plugins > Add New > Upload Plugin**.
4. Upload the `.zip` file and click **Install Now**.
5. Click **Activate**. You will see a new **Course Items** menu in your WordPress sidebar.

---

## 🛠️ Configuration & Usage

Follow these steps to set up your first dynamic course board.

### Step 1: Create a Project (The Environment)
Projects act as containers to keep different boards completely separate.
1. Go to **Course Items > Projects**.
2. Create a new project (e.g., Name: `Microsoft Applied Skills`, Slug: `ms-skills`).

### Step 2: Define Your Filters (Parent/Child Tags)
The plugin uses a Parent/Child relationship to build the sidebar dropdowns. The **Parent** becomes the dropdown title, and the **Children** become the selectable options.
1. Go to **Course Items > Tags / Filters**.
2. **Create the Dropdowns (Parents):** Add a tag like `Skill Level` (Leave parent as "None").
3. **Create the Options (Children):** Add a tag like `Beginner` and set its Parent to `Skill Level`. 

*Note: The frontend color of the tags is automatically generated based on the Parent tag's name, ensuring all "Skill Levels" share the same visual styling.*

### Step 3: Add Course Content
1. Go to **Course Items > Add New Course**.
2. Enter the Course Title in the main title box.
3. In the right sidebar:
   * **Projects:** Check the box for your specific project (e.g., `Microsoft Applied Skills`).
   * **Tags / Filters:** Check the child tags that apply to this course (e.g., `Beginner`, `Cloud & AI`).
4. Scroll down to the **Course Details** meta box below the content editor:
   * **Course Code:** (e.g., `AZ-900`)
   * **Duration:** (e.g., `4 Weeks`)
   * **Explore Button Link:** (e.g., `https://...`)
   * **Show "New" Badge:** Check to display a yellow "NEW" badge on the frontend.
5. Click **Publish**.

### Step 4: Display the Board
Drop the following shortcode onto any WordPress page or Elementor widget. Make sure the `project` attribute matches the exact slug of the project you created in Step 1.

```text
[course_board project="ms-skills" title="Microsoft Applied Skills" subtitle="Filter and find the right courses."]