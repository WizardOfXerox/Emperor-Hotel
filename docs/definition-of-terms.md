# Definition of Terms

This document defines common technical terms used in the Emperor Hotel Reservation System.

| Term | Meaning In This Project |
| --- | --- |
| Admin | A user role that can access the admin dashboard and manage rooms, reservations, payments, and users. |
| API | An Application Programming Interface. This project does not currently expose a separate REST API; PHP pages and forms handle requests directly. |
| Bootstrap | A frontend CSS/JavaScript library used for responsive layout, buttons, forms, tables, alerts, and carousel behavior. |
| Bootstrap Icons | An icon library used for admin sidebar icons and visual UI indicators. |
| CDN | Content Delivery Network. The project previously used CDN links, but browser libraries are now stored locally in `public/assets/vendor/`. |
| Chart.js | A JavaScript charting library used to display dashboard charts. |
| CRUD | Create, Read, Update, Delete. These are the four basic operations for managing records such as rooms, reservations, and users. |
| Database | A structured storage system. This project uses MySQL/MariaDB through XAMPP. |
| DOMDocument | A PHP class used to create and read XML documents. This project uses it for room XML import/export. |
| ERD | Entity Relationship Diagram. It shows database tables and how they relate to one another. |
| Foreign Key | A database field that links one table to another table, such as `reservations.room_id` linking to `rooms.room_id`. |
| Favicon | The small website icon shown in the browser tab. This project uses `emperors-hotel-logo.svg` as the favicon. |
| Form Handler | PHP code that receives and processes submitted HTML form data. |
| Guest | A hotel customer profile used in reservation records. A guest can be connected to a registered user or created by an admin. |
| Hashing | A one-way security process used for passwords. This project uses PHP `password_hash()` and `password_verify()`. |
| JWT | JSON Web Token. It is often used for API authentication, but this project does not currently use JWT because it uses PHP sessions. |
| Localhost | The local computer running the web server, usually accessed through `http://localhost/`. |
| Model | A PHP class that handles database logic for one data area, such as `Room`, `Reservation`, or `Payment`. |
| MySQL | The relational database system used by the project through XAMPP. |
| Offline Mode | A feature where an app works without a server or database connection. This project does not support standalone offline mode. |
| PDO | PHP Data Objects. It is used to connect PHP to the database and run prepared SQL statements. |
| Prepared Statement | A safer way to run SQL queries by separating SQL structure from user-provided values. |
| Primary Key | A unique identifier for a table record, such as `user_id`, `room_id`, or `reservation_id`. |
| Query | A SQL command used to get or change data in the database. |
| Reservation | A booking record that connects a guest, room, check-in date, check-out date, total amount, and status. |
| Responsive Design | A design approach that allows pages to adjust to different screen sizes. Bootstrap helps provide this. |
| Role-Based Access | A permission system where users can access different pages based on their role, such as `admin` or `user`. |
| Session | Server-side login state stored by PHP so the system knows who is currently logged in. |
| SQL | Structured Query Language. It is used to create tables, insert records, update records, delete records, and query records. |
| Tailwind CSS | A utility-first CSS framework. This project does not use Tailwind CSS. |
| React.js | A JavaScript frontend framework. This project does not use React.js. |
| PostgreSQL | A relational database system. This project does not use PostgreSQL; it uses MySQL/MariaDB. |
| XAMPP | A local development package that includes Apache, PHP, and MySQL/MariaDB. |
| XML | Extensible Markup Language. This project uses XML for room import/export. |

## CRUD Examples In The System

| CRUD Operation | Example In The Project |
| --- | --- |
| Create | Admin creates a new room or reservation. |
| Read | Admin views reservation records in a table. |
| Update | Admin edits a room price or reservation status. |
| Delete | Admin deletes a user, room, or reservation record. |

## Authentication Terms

| Term | Explanation |
| --- | --- |
| Login | The process of entering an email and password to access the system. |
| Logout | The process of ending the active PHP session. |
| Registration | The process where a new user account is created. |
| Password Hash | A secure stored version of the password, not the plain text password. |
| Admin Role | A role that can access admin management pages. |
| User Role | A role that can access normal user booking pages. |
