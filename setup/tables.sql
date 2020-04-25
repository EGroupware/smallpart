/**
 * EGroupware - SmallPart - setup definitions
 *
 * @link http://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

CREATE TABLE IF NOT EXISTS `Kurse` (
    `KursID` int(11) NOT NULL AUTO_INCREMENT,
    `KursName` text,
    `KursPasswort` text,
    `KursOwner` int(11) DEFAULT NULL,
    `Organisation` varchar(255) DEFAULT NULL,
    `KursClosed` tinyint(1) DEFAULT '0',
    PRIMARY KEY (`KursID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `KurseUndTeilnehmer` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `KursID` int(11) DEFAULT NULL,
    `UserID` int(11) DEFAULT NULL,
    PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `KursVideoQuestion` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `KursID` int(11) DEFAULT NULL,
    `VideoElementId` text,
    `Question` text,
    PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `LastVideoWorkingOn` (
    `LastVideoWorkingOnId` int(11) NOT NULL AUTO_INCREMENT,
    `UserId` int(11) DEFAULT NULL,
    `LastVideoWorkingOnData` varchar(250) DEFAULT NULL,
    PRIMARY KEY (`LastVideoWorkingOnId`),
    UNIQUE KEY `LastVideoWorkingOn_UserId_uindex` (`UserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loged` (
    `ID` mediumint(9) NOT NULL AUTO_INCREMENT,
    `UserId` int(11) DEFAULT NULL,
    `nickname` tinytext,
    `Time` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `loged_UserId_uindex` (`UserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `test` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `KursID` int(11) DEFAULT NULL,
    `UserID` int(11) NOT NULL,
    `UserNickname` text,
    `VideoElementId` text,
    `StartTime` int(11) DEFAULT '0',
    `StopTime` int(11) DEFAULT '0',
    `AmpelColor` varchar(6) DEFAULT NULL,
    `Deleted` int(11) DEFAULT '0',
    `AddedComment` text NOT NULL,
    `EditedCommentsHistory` text,
    `RelationToID` int(11) DEFAULT NULL,
    `VideoWidth` text NOT NULL,
    `VideoHeight` text NOT NULL,
    `MarkedArea` text NOT NULL,
    `MarkedAreaColor` text,
    `InfoAlert` text,
    PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `VideoList` (
    `VideoListID` int(11) NOT NULL AUTO_INCREMENT,
    `KursID` int(11) DEFAULT NULL,
    `VideoElementId` text,
    `VideoName` text,
    `VideoExtention` tinytext,
    `VideoNameType` text,
    `VideoDate` tinytext,
    `VideoSrc` text,
    PRIMARY KEY (`VideoListID`),
    KEY `VideoList_Kurse_KursID_fk` (`KursID`),
    CONSTRAINT `VideoList_Kurse_KursID_fk` FOREIGN KEY (`KursID`) REFERENCES `Kurse` (`KursID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
