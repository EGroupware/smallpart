-- MariaDB dump 10.17  Distrib 10.4.12-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: egroupware
-- ------------------------------------------------------
-- Server version	10.4.12-MariaDB-1:10.4.12+maria~bionic

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `egw_smallpart_courses`
--
-- WHERE:  course_id=1

LOCK TABLES `egw_smallpart_courses` WRITE;
/*!40000 ALTER TABLE `egw_smallpart_courses` DISABLE KEYS */;
INSERT INTO `egw_smallpart_courses` VALUES (1,'Christophs Testkurs',NULL,5,-1,0);
/*!40000 ALTER TABLE `egw_smallpart_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `egw_smallpart_videos`
--
-- WHERE:  course_id=1

LOCK TABLES `egw_smallpart_videos` WRITE;
/*!40000 ALTER TABLE `egw_smallpart_videos` DISABLE KEYS */;
INSERT INTO `egw_smallpart_videos` VALUES (1,1,'Brain Slices.mp4','2020-05-08 15:28:28','Hier kann eine Aufgabe für die User angelegt werden. Z.B. Rückfragen zum Stoff ...',NULL, 'mp4', '/egroupware/smallpart/setup/brain-slices.mp4');
/*!40000 ALTER TABLE `egw_smallpart_videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `egw_smallpart_comments`
--
-- WHERE:  course_id=1

LOCK TABLES `egw_smallpart_comments` WRITE;
/*!40000 ALTER TABLE `egw_smallpart_comments` DISABLE KEYS */;
INSERT INTO `egw_smallpart_comments` VALUES (1,1,24,1,13,13,'ffffff',0,'[\"Transparenz des Overlays kann gesteuert werden und muss je nach Zielsetzung höher oder niedriger sein.\"]',NULL,NULL,'','[{\"x\":11.25,\"y\":68.89,\"c\":\"ffffff\"},{\"x\":13.75,\"y\":68.89,\"c\":\"ffffff\"},{\"x\":16.25,\"y\":68.89,\"c\":\"ffffff\"},{\"x\":10,\"y\":71.11,\"c\":\"ffffff\"},{\"x\":16.25,\"y\":71.11,\"c\":\"ffffff\"},{\"x\":16.25,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":15,\"y\":80,\"c\":\"ffffff\"},{\"x\":13.75,\"y\":82.22,\"c\":\"ffffff\"}]'),(2,1,24,1,20,20,'ff0000',0,'[\"Verlust des Triggers bei zu schnellen Bewegungen. Du solltest hier langsamer in der Demonstration sein. Man sieht aber auch wie schnell sich das System wieder \\\"einklinkt\\\"\",\"Christoph[24]\",\"Verlust des Triggers lässt sich unabhängig von der Bewegungsgeschwindigkeit bei zu starkem Kippen nicht vermeiden, da das Bild nicht mehr erkannt werden kann, wenn es optisch nur noch ein Strich ist.\"]','[\"\",\"Christoph[24]\",\"Verlust des Triggers bei zu schnellen Bewegungen. Du solltest hier langsamer in der Demonstartion sein. Man sieht aber auch wie schnell sich das System wieder \\\"einklinkt\\\"\"]',NULL,'',NULL),(3,1,24,1,32,32,'ffffff',0,'[\"Hier sucht die Kamera, man kann es am pulsierenden Symbol erkennen.\",\"Test[71]\",\"Ein \\\"n\\\" zu viel.\"]','[\"Christoph[24]\",\"Hier sucht die Kammera, mann kann es am pulsierenden Symbol erkennen.\",\"Christoph[24]\",\"Hier sucht die Kammera, man kann es am pulsierenden Symbol erkennen.\"]',NULL,'','[{\"x\":45,\"y\":33.33,\"c\":\"ff0000\"},{\"x\":52.5,\"y\":33.33,\"c\":\"ff0000\"},{\"x\":58.75,\"y\":42.22,\"c\":\"ff0000\"},{\"x\":40,\"y\":48.89,\"c\":\"ff0000\"},{\"x\":58.75,\"y\":57.78,\"c\":\"ff0000\"},{\"x\":43.75,\"y\":62.22,\"c\":\"ff0000\"},{\"x\":50,\"y\":66.67,\"c\":\"ff0000\"}]'),(4,1,24,1,50,50,'00ff00',0,'[\"Transparenz kann geregelt werden in der App.\"]',NULL,NULL,'',NULL),(5,1,24,1,61,61,'00ff00',0,'[\"Die Triggererkennung kann Seiten unterscheiden.\"]',NULL,NULL,'',NULL),(6,1,24,1,73,73,'00ff00',0,'[\"Durch den Wechsel werden kleine Veränderungen gut sichtbar,\"]',NULL,NULL,'','[{\"x\":41.25,\"y\":66.67,\"c\":\"ff0000\"},{\"x\":42.5,\"y\":66.67,\"c\":\"ff0000\"},{\"x\":43.75,\"y\":66.67,\"c\":\"ff0000\"},{\"x\":40,\"y\":68.89,\"c\":\"ff0000\"},{\"x\":41.25,\"y\":68.89,\"c\":\"ff0000\"},{\"x\":45,\"y\":68.89,\"c\":\"ff0000\"},{\"x\":40,\"y\":71.11,\"c\":\"ff0000\"},{\"x\":45,\"y\":71.11,\"c\":\"ff0000\"},{\"x\":40,\"y\":73.33,\"c\":\"ff0000\"},{\"x\":45,\"y\":73.33,\"c\":\"ff0000\"},{\"x\":41.25,\"y\":75.56,\"c\":\"ff0000\"},{\"x\":43.75,\"y\":75.56,\"c\":\"ff0000\"},{\"x\":42.5,\"y\":77.78,\"c\":\"ff0000\"}]'),(7,1,24,1,84,84,'ffffff',0,'[\"Verlust von grauer Substanz.\"]',NULL,NULL,'','[{\"x\":53.75,\"y\":35.56,\"c\":\"ff0000\"},{\"x\":63.75,\"y\":42.22,\"c\":\"ff0000\"},{\"x\":31.25,\"y\":46.67,\"c\":\"ff0000\"},{\"x\":67.5,\"y\":51.11,\"c\":\"ff0000\"},{\"x\":31.25,\"y\":77.78,\"c\":\"ff0000\"},{\"x\":36.25,\"y\":82.22,\"c\":\"ff0000\"}]'),(8,1,24,1,94,94,'ff0000',0,'[\"Ab hierf könnte man kürzen!\",null,\"Da ist ein \\\"f\\\" zu viel ...\"]',NULL,NULL,'',NULL),(9,1,24,1,8,8,'ffffff',0,'[\"Warum nimmst Du das in die Hand?\",\"Christoph[24]\",\"Nun ja, ich möcht die dynamichen Fähigkeiten zeigen.\",\"Christoph[24]\",\"ein \\\"s\\\" zu wenig\"]','[\"Warum nimmstg Du das indie HAnd?\",\"Warum nimmst Du das in die HAnd?\"]',NULL,'',NULL),(10,1,24,1,10,10,'ffffff',0,'[\"\"]',NULL,NULL,'','[{\"x\":40,\"y\":48.89,\"c\":\"ff0000\"},{\"x\":42.5,\"y\":48.89,\"c\":\"ff0000\"},{\"x\":38.75,\"y\":51.11,\"c\":\"ff0000\"},{\"x\":38.75,\"y\":55.56,\"c\":\"ff0000\"},{\"x\":45,\"y\":55.56,\"c\":\"ff0000\"},{\"x\":41.25,\"y\":57.78,\"c\":\"ff0000\"},{\"x\":45,\"y\":57.78,\"c\":\"ff0000\"}]'),(11,1,71,1,42,42,'ff0000',0,'[\"Testuser neuer Kommentar\\nDein Kommentar enthält keine Frage, somit ist er beantwortet, ich setze ihn auf grün\\nMir fehlt aber, desahlb setze ich noch mal auf Rot!\",\"Christoph[24]\",\"Beim Retweet ist die Farbe nicht änderbar, ggf. geht das für Dozierende ... ist aber eine größere Änderung.\"]','[\"Test[71]\",\"Testuser neuer kommentar\",\"Christoph[24]\",\"Testuser neuer Kommentar\",\"Christoph[24]\",\"Testuser neuer Kommentar\\nDein Kommentar enthält keine Frage, somit ist er beantwortet, ich setze ihn auf grün\"]',NULL,'',NULL),(12,1,5,1,14,14,'ffffff',0,'[\"Ralf\'s Kommentar mit Markierung ;)\"]','[\"Ralf\'s Kommentar mit Markierung\",\"Ralf\'s Kommentar mit Markierung ;)\",\"Ralf\'s Kommentar mit Markierung\"]',NULL,'','[{\"x\":63.75,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":65,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":66.25,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":70,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":73.75,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":78.75,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":80,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":81.25,\"y\":75.56,\"c\":\"ffffff\"},{\"x\":63.75,\"y\":77.78,\"c\":\"ffffff\"},{\"x\":66.25,\"y\":77.78,\"c\":\"ffffff\"},{\"x\":68.75,\"y\":77.78,\"c\":\"ffffff\"},{\"x\":71.25,\"y\":77.78,\"c\":\"ffffff\"},{\"x\":73.75,\"y\":77.78,\"c\":\"ffffff\"},{\"x\":78.75,\"y\":77.78,\"c\":\"ffffff\"},{\"x\":63.75,\"y\":80,\"c\":\"ffffff\"},{\"x\":65,\"y\":80,\"c\":\"ffffff\"},{\"x\":66.25,\"y\":80,\"c\":\"ffffff\"},{\"x\":68.75,\"y\":80,\"c\":\"ffffff\"},{\"x\":71.25,\"y\":80,\"c\":\"ffffff\"},{\"x\":73.75,\"y\":80,\"c\":\"ffffff\"},{\"x\":78.75,\"y\":80,\"c\":\"ffffff\"},{\"x\":63.75,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":65,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":68.75,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":70,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":71.25,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":73.75,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":78.75,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":80,\"y\":82.22,\"c\":\"ffffff\"},{\"x\":63.75,\"y\":84.44,\"c\":\"ffffff\"},{\"x\":66.25,\"y\":84.44,\"c\":\"ffffff\"},{\"x\":68.75,\"y\":84.44,\"c\":\"ffffff\"},{\"x\":71.25,\"y\":84.44,\"c\":\"ffffff\"},{\"x\":73.75,\"y\":84.44,\"c\":\"ffffff\"},{\"x\":78.75,\"y\":84.44,\"c\":\"ffffff\"},{\"x\":63.75,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":66.25,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":68.75,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":71.25,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":73.75,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":75,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":76.25,\"y\":86.67,\"c\":\"ffffff\"},{\"x\":78.75,\"y\":86.67,\"c\":\"ffffff\"}]');
/*!40000 ALTER TABLE `egw_smallpart_comments` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-05-12 14:25:19
