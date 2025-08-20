# 📊 Network Tools Dashboard

This dashboard provides real-time insights into usage of the public network utilities hosted at [IT-INDIA.org](https://www.it-india.org/netools/). It is powered by the `logs` table in the `itindiaorg_netools_db` database.

---

## 🗂️ Table Schema: `logs`

| Column Name | Type         | Null | Default             | Extra           | Description |
|-------------|--------------|------|---------------------|------------------|-------------|
| `id`        | `int(11)`    | No   | —                   | `AUTO_INCREMENT` | Primary key |
| `test_type` | `varchar(32)`| Yes  | `NULL`              | —                | Type of test (`Ping`, `Traceroute`, etc.) |
| `target`    | `varchar(64)`| Yes  | `NULL`              | —                | Domain or IP tested |
| `result`    | `text`       | Yes  | `NULL`              | —                | Raw output of the test |
| `ip`        | `varchar(45)`| Yes  | `NULL`              | —                | Requester's IP address |
| `country`   | `varchar(2)` | Yes  | `NULL`              | —                | ISO country code |
| `timestamp` | `timestamp`  | Yes  | `current_timestamp()`| —               | Auto-filled when test is logged |

**Indexes**:
- `PRIMARY KEY` on `id`
- `KEY idx_timestamp` on `timestamp`

---

## 🔐 Authentication

- Basic HTTP authentication (`admin` / `your_secure_password`)
- Change credentials directly in `dashboard.php` before deployment

---

## 📈 Key Features

### 📊 Comprehensive Statistics
- Total test count
- Tests by type (pie chart)
- Top targets list
- Geographic distribution (bar chart)

### 📋 Recent Activity Table
- Paginated (20 results per page)
- Filterable by test type and date range
- View full results in a modal popup

### 🎨 Visualizations
- Pie chart: test type distribution
- Bar chart: country distribution
- Responsive layout using Bootstrap 5

---

## 🔒 Security

- All output properly escaped
- Prepared statements for all DB queries
- Basic authentication to restrict access

---

## 🚀 How to Use

1. Upload `dashboard.php` to your server
2. Access it at: [https://www.it-india.org/netools/dashboard.php](https://www.it-india.org/netools/dashboard.php)
3. Log in with:
   - Username: `admin`
   - Password: `your_secure_password` (change this!)

---

## 🧭 Next Steps (Optional Enhancements)

- Replace basic auth with a proper login system
- Add CSV/Excel export for logs
- Use GeoIP database for better country detection
- Set up alerts for suspicious activity
- Add multi-user admin support

---

## 📄 Related Files

- [`schema.sql`](./schema.sql): SQL definition of the `logs` table
- [`README.md`](./README.md): Project overview and usage
