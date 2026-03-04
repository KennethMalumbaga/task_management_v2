# Task Management V2

## User Guide

For end-user/subscriber flow documentation, read:

- `README-USER-GUIDE.md`

## Latest Update Compilation

For a compiled summary of updates completed today, read:

- `README-UPDATE-2026-02-20.md`
- `README-UPDATE-2026-03-05.md`

## How to Merge Upstream Changes (Safe Method)

If you have forked this repository and want to pull the latest changes from the original (upstream) repo without breaking your local setup, follow these steps to do a "test run" first.

### 1. Fetch the latest updates
Download the latest history from the upstream repository without merging anything yet.
```bash
git fetch upstream
```

### 2. Create a test branch
Create a new branch from your current code to test the merge safely.
```bash
git checkout -b test-merge-upstream
```

### 3. Merge upstream changes into the test branch
Apply the updates to your test branch.
```bash
git merge upstream/main
```

### 4. Verify
Run your application and check if everything is working correctly.
- If it works: You can merge this branch into your main branch.
- If it breaks: You can simply delete this branch and your `main` branch remains safe.

### 5. Finalize (If successful)
If the test run was successful, switch back to your main branch and merge the changes.
```bash
git checkout main
git merge test-merge-upstream
git push origin main
```
