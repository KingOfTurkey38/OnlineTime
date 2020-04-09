<?php

declare(strict_types=1);

namespace KingOfTurkey38\OnlineTime;

use DateTime;
use Exception;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as C;
use SQLite3;

class Main extends PluginBase implements Listener
{

    /** @var array */
    private $joins;

    /** @var SQLite3 */
    private $database;

    public function onEnable(): void
    {
        $this->database = new SQLite3($this->getDataFolder() . "onlinetime.db");
        $this->database->query("CREATE TABLE IF NOT EXISTS OnlineTime (username VARCHAR(50) NOT NULL , onlinetime INTEGER DEFAULT 0, PRIMARY KEY (username))");

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $stmt = $this->database->prepare("INSERT or IGNORE INTO OnlineTime (username) VALUES (:username)");
        $stmt->bindValue(":username", $player->getName());
        $stmt->execute();

        $this->joins[$player->getName()] = time();
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $this->saveTime($event->getPlayer()->getName());
    }

    private function saveTime(string $username): void
    {
        if (isset($this->joins[$username])) {
            $played = time() - $this->joins[$username];
            $stmt = $this->database->prepare("UPDATE OnlineTime SET onlinetime = onlinetime + :played WHERE LOWER(username)=:username");
            $stmt->bindValue(":played", $played);
            $stmt->bindValue(":username", strtolower($username));
            $stmt->execute();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "onlinetime":
                $query = $this->database->query("SELECT * FROM OnlineTime order by onlinetime DESC");
                $data = [];

                while ($r = $query->fetchArray()) {
                    $data[] = $r;
                }

                $size = sizeof($data);
                $pages = ceil($size / 10);

                if (isset($args[0])) {
                    if (is_numeric($args[0])) {
                        $page = abs($args[0]);
                        if ($page <= $pages) {
                            $numbers = range($page * 10 - 9, $page * 10);
                            $sender->sendMessage(C::GRAY . "Showing players with the most playtime " . C::RED . $page . C::GRAY . "/" . C::RED . $pages);
                            foreach ($numbers as $number) {
                                if (isset($data[$number])) {
                                    $sender->sendMessage(C::AQUA . C::BOLD . " - " . C::RESET . C::YELLOW . "#$number " . C::WHITE . $data[$number]["username"] . C::GRAY . ": " . C::RED . $this->secondsToTime($data[$number]["onlinetime"]));
                                }
                            }
                            return true;
                        } else {
                            $sender->sendMessage("No OnlineTime data found to display on this page");
                            return true;
                        }
                    }
                }

                $numbers = range(0, 9);
                $sender->sendMessage(C::GRAY . "Showing players with the most playtime " . C::RED . "1" . C::GRAY . "/" . C::RED . $pages);
                foreach ($numbers as $number) {
                    if (isset($data[$number])) {
                        $ranking = $number + 1;
                        $sender->sendMessage(C::AQUA . C::BOLD . " - " . C::RESET . C::YELLOW . "#$ranking " . C::WHITE . $data[$number]["username"] . C::GRAY . ": " . C::RED . $this->secondsToTime($data[$number]["onlinetime"]));
                    }
                }

                return true;
            default:
                return false;
        }
    }

    /**
     * @param int $seconds
     * @return string
     * @throws Exception
     * Love copied code https://stackoverflow.com/questions/8273804/convert-seconds-into-days-hours-minutes-and-seconds
     */
    private function secondsToTime(int $seconds)
    {
        $dtF = new DateTime('@0');
        $dtT = new DateTime("@$seconds");
        return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
    }

    public function onDisable(): void
    {
        if (!empty($this->joins)) {
            foreach ($this->joins as $key => $played) {
                $this->saveTime($key);
            }
        }
    }
}
