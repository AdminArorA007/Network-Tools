# 🧰 Network Diagnostics Tool

Run basic network tests like ping from your browser—no login, no ads.

## 🔗 Live Demo
Try it now: [https://www.it-india.org/netools/](https://www.it-india.org/netools/)

## ✨ Features
- 🌐 Ping any hostname or IP address
- 🧭 Run Traceroute to map network hops
- 🧠 Perform DNS lookups to resolve domains
- 🔓 Scan ports to check service availability
- ⏱️ Rate-limited to prevent abuse
- 📄 View results instantly in-browser

## 📊 Live Dashboard
Track usage and test history in real time.
- View top targets and test types
- Filter by date or test category
- See recent activity with IP and country data

🔗 [Network Tools Dashboard](https://www.it-india.org/netools/dashboard.php)


## 🚀 Tech Stack
- PHP backend
- HTML/CSS/JS frontend
- Hosted on [IT-INDIA.org](https://www.it-india.org/)


🔧 Configuration: Set Your Database Credentials
To run the dashboard locally or on your own server, update the following three files with your database connection details:

File Path	        What to Update

config.php	        Set DBhost, DBuser, DBpass, and DBname
dashboard.php	    Confirm the same credentials are passed or included (also change login password on line #8 )
db/dbconnect.php	Ensure the connection logic uses your updated values
 

## 📜 License
MIT — free to use, modify, and share.
