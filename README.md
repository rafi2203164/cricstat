# 🏏 CricStat — Cricket Scoring System

A web-based cricket scoring and tournament management system built with PHP and MySQL. Supports ball-by-ball match scoring, live statistics, points table, and player performance tracking.

---

## Features

- Password protected admin login
- Create and manage tournaments
- Add teams and players with configurable squad size
- Ball by ball match scoring with full cricket rules
- Wide, No Ball, Bye, Leg Bye, Free Hit support
- Wicket types — Bowled, Caught, LBW, Stumped, Hit Wicket, Run Out
- Undo last ball at any point
- Two innings with target setting and result detection
- Live score, Current Run Rate, Required Run Rate
- Ball timeline, Fall of Wickets
- Full batting and bowling scorecards
- Points table with NRR, Win/Loss/Tie tracking
- Most Runs and Most Wickets statistics
- Delete tournaments with all associated data

---

## Tech Stack

- **Backend:** PHP (vanilla, no framework)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS (custom dark theme)
- **Server:** Apache via XAMPP
- **Storage:** Ball-by-ball data — all stats calculated dynamically from raw data

---

## Requirements

- XAMPP (or any Apache + MySQL + PHP setup)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Browser (Chrome, Firefox, Edge)

---

## Installation

### Step 1 — Clone the repository

```bash
git clone https://github.com/rafi2203164/cricstat.git
```

Place the `cricstat` folder inside your XAMPP `htdocs` directory:

```
C:/xampp/htdocs/cricstat/
```

### Step 2 — Set up the database

1. Start **Apache** and **MySQL** from XAMPP Control Panel
2. Open `http://localhost/phpmyadmin`
3. Create a new database named `cricstat`
4. Click on the `cricstat` database
5. Go to the **SQL** tab
6. Open `cricstat.sql`, copy all contents, paste and click **Go**

### Step 3 — Set your password

Open `config.php` and change the password:

```php
define('ADMIN_PASSWORD', 'your_password_here');
```

### Step 4 — Run the app

Open your browser and go to:

```
http://localhost/cricstat/login.php
```

Enter your password and you're in.

---

## Usage Flow

1. **Create Tournament** — set tournament name and squad size (players per team)
2. **Add Teams** — add teams and fill each team's squad with players
3. **Create Match** — select two teams and set number of overs
4. **Score Match** — score ball by ball, handle extras and wickets
5. **View Results** — check scorecard, points table, and player statistics

---

## Project Structure

```
cricstat/
├── assets/
│   └── css/
│       └── style.css          # Dark theme stylesheet
├── matches/
│   ├── create_match.php       # Create a new match
│   ├── score_match.php        # Ball by ball scoring engine
│   └── scorecard.php          # Full match scorecard
├── stats/
│   ├── most_runs.php          # Top run scorers
│   └── most_wickets.php       # Top wicket takers
├── teams/
│   └── add_team.php           # Add teams and players
├── tournaments/
│   ├── create_tournament.php  # Create tournament
│   └── points_table.php       # Tournament points table
├── auth.php                   # Session authentication
├── config.php                 # Password configuration
├── db.php                     # Database connection
├── index.php                  # Home dashboard
├── login.php                  # Login page
├── logout.php                 # Logout
└── cricstat.sql               # Database schema
```

---

## Database Schema

| Table         | Description                           |
| ------------- | ------------------------------------- |
| `tournaments` | Tournament name and squad size        |
| `teams`       | Teams linked to tournaments           |
| `players`     | Players linked to teams               |
| `matches`     | Match details, overs, innings, result |
| `balls`       | Ball by ball delivery data            |

All statistics (runs, wickets, economy, strike rate, NRR) are calculated dynamically from the `balls` table — no aggregated data is stored.

---

## Developer

**Rafi** — CSE, RUET  
GitHub: [github.com/rafi2203164](https://github.com/rafi2203164)

---

## License

This project is for educational purposes.
