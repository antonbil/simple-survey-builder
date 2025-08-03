# Simple Survey Builder

**A straightforward WordPress plugin for creating and managing surveys, designed with flexibility and control in mind.**

Simple Survey Builder allows you to quickly and easily configure surveys using a `JSON` configuration file and display them on your WordPress website via simple shortcodes. It stores survey submissions in dedicated custom post types, giving you clear ownership and access to your data.

## Table of Contents

*   [Overview](#overview)
*   [Key Features](#key-features)
*   [Considerations for Number of Submissions](#considerations-for-number-of-submissions)
*   [Installation](#installation)
*   [Usage](#usage)
    *   [Configuration (`survey_configurations.json`)](#configuration-survey_configurationsjson)
    *   [Shortcodes](#shortcodes)
        *   [Display a Survey: `[simple_survey]`](#1-display-a-survey-simple_survey)
        *   [Display Survey Results: `[site_survey_results]`](#2-display-survey-results-site_survey_results)
        *   [List Survey Entries: `[cpt_list]`](#3-list-survey-entries-cpt_list)
*   [About the Author](#about-the-author)
*   [Screenshots (Optional)](#screenshots-optional)
*   [Contributing](#contributing)
*   [Changelog](#changelog)
*   [License](#license)
*   [Support](#support)

## Overview

This plugin was initially developed in a short timeframe (about a week!) to address a personal need for creating straightforward surveys, specifically for evaluating a website and gathering feedback on a newsletter. The positive experience and the utility of the approach led to its further development.

**Who is this plugin for?**

*   **Developers & Technically Savvy Administrators:** If you're comfortable working with `JSON` files for configuration and appreciate the transparency of having survey data stored as custom post types within your own WordPress database, this plugin will feel right at home. It offers a good degree of control without the overhead of complex UI-based builders for scenarios where you need direct configuration access.
*   **Users needing simple, configurable surveys:** If you need to quickly deploy surveys for specific, targeted feedback and prefer a no-fuss setup once configured.
*   **Those who value data ownership:** All survey structures and submissions reside within your WordPress environment.

**Considerations for Number of Submissions:**

Since submissions are stored as individual posts within a custom post type, WordPress itself is quite capable of handling a large number of posts. Performance will generally depend more on your hosting environment, database optimization, and how efficiently queries are made (e.g., when displaying results or listing entries).

*   For **displaying aggregated results** (`[site_survey_results]`), the plugin queries all submissions for that survey to generate statistics. With a very high number of submissions (e.g., tens of thousands for a single survey), this aggregation step could become resource-intensive on lower-end hosting.
*   For **listing entries** (`[cpt_list]`), standard WordPress pagination is used, so listing is generally efficient.
*   The primary WordPress limit would eventually be database size and query performance, but for typical survey usage (hundreds to a few thousand submissions per survey), this approach is generally robust. If you anticipate extremely high volumes (e.g., hundreds of thousands of submissions for a *single* survey being queried frequently for aggregation), you might explore more specialized data storage or caching strategies, but this typically falls outside the scope of a "simple" survey plugin. For most common use cases, this plugin should perform well.

## Key Features

*   **JSON-based Configuration:** Define your survey questions, types (text, select, radio, checkbox), and associated custom post types in a clear `survey_configurations.json` file. This allows for easy version control and programmatic modification if needed.
*   **Custom Post Type Storage:** Each survey configuration can (and typically does) map to its own custom post type for storing submissions. This keeps data organized and accessible using standard WordPress tools and functions.
*   **Shortcode Driven:** Easy to embed surveys, display aggregated results (including charts), and list individual submissions using simple shortcodes:
    *   `[simple_survey slug="your_survey_slug"]` - Displays the survey form.
    *   `[site_survey_results slug="your_survey_slug"]` - Shows aggregated results.
    *   `[cpt_list slug="your_survey_slug"]` - Lists individual submissions for a survey.
*   **Flexible Reporting & Visualization:** Provides robust options for viewing survey results directly on your website:
    *   **Aggregated Data Display:** The `[site_survey_results]` shortcode presents a summary of all responses for a survey.
    *   **Chart Integration:** Out-of-the-box support for visualizing multiple-choice answers as **bar charts** (using Chart.js). The structure is designed to be extensible for other chart types in the future.
    *   **List Individual Submissions:** The `[cpt_list]` shortcode allows for displaying and paginating through all individual survey entries.
*   **Developer-Friendly & Extensible:** Built with WordPress best practices, making it a good base for further customization or adding new reporting features if required.
*   **Basic Styling Included:** Comes with a basic CSS file that you can extend or override.
*   **Lightweight and Fast:** Designed to have a minimal impact on your site's loading time.

## About the Author

I'm a retired IT professional with a long history in ICT programming. I enjoy applying my skills to create practical and useful tools for myself and others who might find them beneficial. This plugin is a product of that passion – taking a specific need and building a straightforward, effective solution. My focus is often on creating tools that are transparent in their operation and provide a good level of control to those who are comfortable looking "under the hood."


## Installation

There are a couple of ways to install the Simple Survey Builder plugin:

**1. Using Git (Recommended for developers):**

   a. Navigate to your WordPress plugins directory: `wp-content/plugins/`
   b. Clone this repository:
     `bash git clone https://github.com/your- username/ simple- survey- builder. git`
   c. Activate the plugin through the 'Plugins' menu in WordPress.

**2. Manual Installation (Download ZIP):**

   a. Go to the main page of this GitHub repository: [https://github.com/your-username/simple-survey-builder](https://github.com/your-username/simple-survey-builder)
   b. Click the green "Code" button, then click "Download ZIP".
   c. In your WordPress admin dashboard, go to `Plugins > Add New`.
   d. Click `Upload Plugin` at the top.
   e. Choose the `.zip` file you downloaded and click `Install Now`.
   f. Activate the plugin.
   
## Usage

### Configuration

1.  After activating the plugin, locate the `config` directory within the plugin folder (`wp-content/plugins/simple-survey-builder/config/`).
2.  Here you will find the `survey_configurations.json` file. This is where you define all your surveys.
3.  **Structure of `survey_configurations.json`:**
```json
{
    "your_survey_slug_1": {
        "survey_title": "Title of Survey 1",
        "survey_introduction": "<p>This is an introductory text for Survey 1.</p>",
        "survey_questions_config": {
            "question_key_1": {
                "form_label": "What is your name?",
                "form_type": "text",
                "required": true,
                "placeholder": "Your full name"
            },
            "question_key_2": {
                "form_label": "How satisfied are you?",
                "form_type": "radio",
                "required": true,
                "form_options": {
                    "very_satisfied": "Very satisfied",
                    "satisfied": "Satisfied",
                    "neutral": "Neutral",
                    "dissatisfied": "Dissatisfied"
                }
            }
            // ... more questions can be added here, following the same pattern
        },
        "submit_button_text": "Submit Survey 1",
        "thank_you_message": "<p>Thank you for completing Survey 1!</p>"
    },
    "your_survey_slug_2": {
        "survey_title": "Title of Survey 2",
        "survey_introduction": "<p>Introduction for the second survey.</p>",
        "survey_questions_config": {
            "another_question": {
                "form_label": "Any comments?",
                "form_type": "textarea",
                "required": false,
                "placeholder": "Enter your comments here..."
            }
            // ... more questions for survey 2
        },
        "submit_button_text": "Submit Survey 2",
        "thank_you_message": "<p>Thanks for your input on Survey 2!</p>"
    }
    // ... you can add more survey configurations here, separated by commas
}
```
        *   **`your_survey_slug_1`**: A unique identifier (slug) for your survey. You'll use this in the shortcode.
        *   **`survey_title`**: The title displayed at the top of the survey.
        *   **`survey_introduction`**: HTML text that serves as an introduction.
        *   **`survey_questions_config`**: An object containing the configuration for each question.
            *   **`question_key_1`**: A unique key for the question.
            *   **`form_label`**: The text of the question.
            *   **`form_type`**: The input type (`text`, `textarea`, `select`, `radio`, `checkbox`).
            *   **`required`**: `true` or `false`.
            *   **`placeholder`**: Placeholder text for text/textarea.
            *   **`form_options`**: An object with `value: label` pairs for select, radio, and checkbox types.
        *   **`submit_button_text`**: The text on the submit button.
        *   **`thank_you_message`**: HTML text displayed after successful submission.

### Shortcodes

This plugin provides several shortcodes to display surveys, view results, and list survey entries.

### 1. Display a Survey: `[simple_survey]`

This shortcode is used to display a specific survey form on any page, post, or widget.

**Usage:**
`[simple_survey slug="your_survey_slug" ]`
**Parameters:**

*   `slug` (required): This is the unique identifier for the survey you want to display. It must match one of the `slug_identifier` values defined in your `config/survey_configurations.json` file.

**Functionality:**

When this shortcode is used, the plugin will:

1.  Load the survey configuration associated with the provided `slug` from the `config/survey_configurations.json` file.
2.  Dynamically generate and display the HTML form for the survey, including all questions, input fields (text, select, radio, checkbox), and labels as defined in the configuration.
3.  Handle the submission of this form. Upon submission, the data is validated and saved as a new entry in the custom post type associated with that survey configuration.

**Example:**

If you have a survey configuration in `config/survey_configurations.json` with `slug_identifier: "website_feedback"`, you would use the following shortcode to display it:
`[simple_survey slug="website_feedback" ]`
---

### 2. Display Survey Results: `[site_survey_results]`

This shortcode is used to display a dynamic and aggregated summary of the results for a specific survey, offering clear visual insights into the collected data. It typically shows charts for multiple-choice questions and can list open-ended answers.

**Usage:**
`[site_survey_results slug="your_survey_slug" ]`
**Parameters:**

*   `slug` (required): This is the `slug_identifier` of the survey for which you want to display the results (defined in your `config/survey_configurations.json` file). This tells the plugin which survey's data to fetch and aggregate.

**Functionality:**

When this shortcode is used, the plugin will:

1.  Identify the custom post type associated with the given `slug` (based on your `config/survey_configurations.json`).
2.  Fetch all submitted entries for that survey from the database.
3.  Aggregate the answers:
    *   For multiple-choice questions (like radio buttons, select dropdowns, or checkboxes), it calculates the count for each option.
    *   For open-ended questions (like text fields or textareas), it may list a selection of the responses or provide a summary.
4.  Display the aggregated results, with a strong emphasis on clear reporting:
    *   **Visual Charts:** Automatically generates **bar charts** (via Chart.js) for multiple-choice questions (radio buttons, select dropdowns, checkboxes), providing an immediate visual understanding of response distribution. The plugin's architecture is prepared for potential expansion with other chart types.
    *   **Textual Data:** Lists or summarizes answers from open-ended questions (text fields, textareas).
    *   The aim is to provide comprehensive and easily digestible reports directly on your WordPress frontend.
5.  The exact presentation of results (which questions are charted, how open answers are displayed) is determined by the plugin's result processing logic for that specific survey type.

**Example:**

To display the aggregated results for the survey identified by `slug_identifier: "product_satisfaction_survey"`:
`[site_survey_results slug="product_satisfaction_ survey" ]`
---
**Parameters:**

*   `slug` (required): This is the unique **`slug_identifier`** for the survey configuration (defined in your `config/survey_configurations.json` file). This tells the plugin which survey's entries to list by looking up its associated Custom Post Type.
*   `number` (optional): The maximum number of entries to display per page. Defaults to `10`.
*   `orderby` (optional): How to sort the entries. Accepts standard WordPress orderby parameters (e.g., `date`, `title`, `ID`, `rand`). Defaults to `date`.
*   `order` (optional): The direction of the sorting. Accepts `ASC` (ascending) or `DESC` (descending). Defaults to `DESC`.
*   `link_to` (optional): Determines if and how entries are linked.
    *   `admin_edit`: (Default) Links the entry title to the WordPress admin edit screen for that entry (if the current user has permission).
    *   `none`: Displays the entry title without a link.
    *(Future options might include 'public_view' if individual entries are made publicly viewable).*

**Functionality:**

When this shortcode is used, the plugin will:

1.  Load the survey configuration associated with the provided `slug` (the `slug_identifier`) from the `config/survey_configurations.json` file.
2.  From this configuration, it determines the slug of the actual Custom Post Type (CPT) where this survey's entries are stored (this is the `post_type_definition.slug` value in your JSON).
3.  Query the WordPress database for all posts belonging to that specific CPT, using any provided optional parameters for ordering and pagination.
4.  Display a list of these posts. For each entry, it typically shows:
    *   The title of the entry (often a timestamp or a generic "Survey Submission").
    *   A link to edit the entry in the WordPress admin, or no link, based on the `link_to` attribute and user permissions.
    *   The submission date.
    *   Optionally, a selection of relevant custom fields from the submission (e.g., an "Overall Satisfaction" rating if applicable to that survey type).
5.  Pagination links will be displayed if there are more entries than the `number` specified.

This shortcode provides a way to see an overview of all individual submissions for a survey, directly on the frontend of your site.

**Example:**

If you have a survey configuration in `config/survey_configurations.json` with `slug_identifier: "newsletter_feedback_march2024"`, you would use the following shortcode to list its entries:
`[cpt_list slug="newsletter_feedback_ march2024" ]`
To show the 5 newest entries, with titles linking to the admin edit screen:
`[cpt_list slug="newsletter_feedback_ march2024"  number="5" order="DESC" orderby="date" link_to="admin_edit" ]`

### 3. List Survey Entries: `[cpt_list]`

This shortcode is used to display a list of individual entries submitted for a specific survey configuration. It essentially lists posts from the custom post type that is associated with that survey in your configuration.

**Usage:**
`[cpt_list slug="your_survey_slug"  number="10" orderby="date" order="DESC" link_to="admin_edit" ]`

## Screenshots (Optional)

*(If you have screenshots of how the plugin looks or works, add them here. This can help make the plugin visually appealing to users.)*

**Example of a survey:**
`![Example Survey](link/to/screenshot1.png)`

**Configuration in JSON:**
`![Example JSON](link/to/screenshot2.png)`

## Contributing

Contributions are welcome! Whether it's reporting bugs, suggesting new features, or contributing code.

1.  Fork the repository.
2.  Create a new branch for your feature or bugfix (`git checkout -b feature/awesome-feature` or `git checkout -b fix/a-bug`).
3.  Commit your changes (`git commit -am 'Add an awesome feature'`).
4.  Push to the branch (`git push origin feature/awesome-feature`).
5.  Create a new Pull Request.

Please ensure your code adheres to the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).

## Changelog

See the [CHANGELOG.md](CHANGELOG.md) for a detailed history of changes in each version. *(You will need to create this file and maintain it.)*

## License

This plugin is licensed under the GPL v2 or later.
See [LICENSE.txt](LICENSE.txt) (or link to https://www.gnu.org/licenses/gpl-2.0.html). *(You might want to add a LICENSE.txt file to your repository).*

## Support

If you have any questions or encounter any issues, please open an issue on the [GitHub repository issue tracker](https://github.com/your-username/simple-survey-builder/issues).
*(You can also add other support channels if you plan to offer them, e.g., a support forum on wordpress.org if your plugin is listed there).*
