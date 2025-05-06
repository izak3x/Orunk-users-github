# Orunk Users GitHub Plugin

This repository contains the code for the Orunk Users plugin (and potentially related plugins like the Bins API plugin) for WordPress.

**Repository Link:** [https://github.com/izak3x/Orunk-users-github](https://github.com/izak3x/Orunk-users-github)

*(Add a short description here about what the Orunk Users plugin does)*

## Getting Started / Setup

*(Add basic setup instructions here. For example, if it's a standard WordPress plugin installation):*

1.  Download the plugin files (e.g., as a ZIP from the repository).
2.  In your WordPress admin dashboard, go to `Plugins` > `Add New` > `Upload Plugin`.
3.  Upload the ZIP file and activate the plugin.
4.  Configure any necessary settings under *(mention where settings are, e.g., Settings > Orunk Users)*.

## How to Contribute

We welcome contributions to fix bugs or add new features! Since you might be new to this, here’s a simple process:

**1. Reporting Bugs or Suggesting Features (The Easy Way)**

* If you find a problem (a bug) or have an idea for a new feature, the best first step is to tell us about it.
* Go to the **"Issues"** tab at the top of the repository page: [https://github.com/izak3x/Orunk-users-github/issues](https://github.com/izak3x/Orunk-users-github/issues)
* Click the green **"New Issue"** button.
* **For Bugs:** Give it a clear title (e.g., "Error when saving user profile"). Describe the problem in detail: What did you do? What did you expect to happen? What actually happened? Include any error messages if possible.
* **For Features:** Give it a clear title (e.g., "Add option to export users"). Describe the feature you would like to see and why it would be helpful.

**2. Making Code Changes (If You Want to Fix/Add Something Yourself)**

This involves using GitHub and GitHub Desktop. It seems complex at first, but it keeps things organized.

* **Step A: Make a Copy (Fork)**
    * Go to the main repository page: [https://github.com/izak3x/Orunk-users-github](https://github.com/izak3x/Orunk-users-github)
    * Click the **"Fork"** button in the top-right corner. This creates your own personal copy of the repository under your GitHub account.

* **Step B: Get the Code on Your Computer (Clone)**
    * Go to **your forked repository** page on GitHub (it will look like `https://github.com/YourUsername/Orunk-users-github`).
    * Click the green **`< > Code`** button.
    * Click **"Open with GitHub Desktop"**.
    * Follow the prompts in GitHub Desktop to save (clone) the repository to a folder on your computer.

* **Step C: Edit the Files**
    * Find the repository folder on your computer that GitHub Desktop just created.
    * Open the project files (likely inside the `orunk-users` subfolder) in your code editor.
    * Find the lines you need to change or add the new code for the fix or feature.
    * **Save your changes** in your code editor.

* **Step D: Save Your Changes to Git History (Commit)**
    * Open GitHub Desktop.
    * It should show your changed files in the **"Changes"** tab.
    * Select the files you want to save (usually all of them).
    * Write a clear **"Summary"** message describing your change (e.g., "Fix typo in login form", "Add setting for XYZ").
    * Click the blue **`Commit to main`** button.

* **Step E: Upload Your Changes to YOUR Fork (Push)**
    * After committing, click the **`Push origin`** button at the top of GitHub Desktop. This uploads your committed changes to *your forked copy* on GitHub.com (not the original `izak3x` one yet).

* **Step F: Propose Your Changes (Pull Request)**
    * Go back to **your forked repository** page on GitHub.com.
    * You should see a yellow bar suggesting you create a "Pull Request". Click the **"Compare & pull request"** button. (Or go to the "Pull requests" tab and click "New pull request").
    * Make sure the base repository is `izak3x/Orunk-users-github` and the head repository is your fork.
    * Write a title and description explaining what your changes do. If it fixes an Issue you opened earlier, mention the issue number (e.g., "Fixes #123").
    * Click **"Create pull request"**.

Now, the owner of the main repository (`izak3x`) can review your changes and merge them into the official project if they look good!

## Main Plugin Code Location

The primary code for the user management plugin seems to be located in the `/orunk-users/` directory within this repository.

---

**How to Add This `README.md` File to Your Repository:**

1.  **Create the File:** On your computer, go into the main `Orunk-users-github` folder that GitHub Desktop uses. Create a new plain text file right in that main folder (not inside `orunk-users` or other subfolders) and name it exactly `README.md`.
2.  **Copy/Paste:** Copy *all* the code block above (starting with `# Orunk Users GitHub Plugin` and ending with `within this repository.`). Paste it into the `README.md` file you just created.
3.  **Edit Placeholders:** Read through the pasted text and fill in the parts that say *(Add... here)* with your own project details.
4.  **Save:** Save the `README.md` file.
5.  **Commit & Push:**
    * Open GitHub Desktop.
    * It should show `README.md` in the "Changes" tab.
    * Select it.
    * In the "Summary" box, type something like `Add README file with contribution guide`.
    * Click `Commit to main`.
    * Click `Push origin`.

Now, when you visit `https://github.com/izak3x/Orunk-users-github`, you should see this information displayed nicely on the main page! This will guide anyone wanting to help.
