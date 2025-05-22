# SlackDB

SlackDB is a simple web application for searching and exploring SlackBuilds from the SlackBuilds.org repository for Slackware Linux. You can search for packages, view their descriptions, dependencies, and see which other packages require them.

## Features

- Fast search for SlackBuilds by name.
- Shows package details, including version, description, download links, and MD5 checksums.
- Displays dependencies ("Requires") and reverse dependencies ("Required by").
- Direct links to the official SlackBuilds.org repository.

## Data

All package data is sourced from the public SlackBuilds.org repository. The data is free and open for anyone to use.

## License

This project is open source and free to use.

## Running Locally (Development Server)

For testing or development purposes, you can use PHP's built-in web server. Navigate to the project's root directory in your terminal and run the following command:

```bash
php -S localhost:8000
```

This will start a web server listening on `localhost` on port `8000`. You can then access the application in your web browser at `http://localhost:8000`.
