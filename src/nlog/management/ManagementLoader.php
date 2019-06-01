<?php

declare(strict_types=1);

namespace nlog\management;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;


class ManagementLoader extends PluginBase {

    /** @var array */
    public static $plugins = [];

    public static function isValidPlugin(string $name) {
        return isset(self::$plugins[$name]);
    }

    /** @var null|ManagementLoader */
    private static $instance = \null;

    public static function getInstance(): ?ManagementLoader {
        return self::$instance;
    }

    /** @var array */
    private const SETTING_DEFAULT = [
            'GuildPlus' => true,
            'GuildExtension' => true,
            'AdvancedPrefix' => true,
            'PrefixAddon' => true
    ];

    public static function getTimeUnit(int $time) {
        $str = '';
        $seconds = floor($time % 60);

        $minutes = -1;
        $hours = -1;
        $days = -1;
        $month = -1;
        $year = -1;


        if ($time >= 60) {
            $minutes = floor(($time % 3600) / 60);
            if ($time >= 3600) {
                $hours = floor(($time % (3600 * 24)) / 3600);
                if ($time >= 3600 * 24) {
                    $days = floor(($time % (3600 * 24 * 30)) / (3600 * 24));
                    if ($time >= 3600 * 24 * 30) { // 한달을 30일로 계산
                        $month = floor(($time % (3600 * 24 * 30 * 12)) / (3600 * 24 * 30));
                        if ($time >= 3600 * 24 * 365) {
                            $year = floor($time / (3600 * 24 * 365));
                            if ($year > 0) {
                                $str .= "{$year}년 ";
                            }
                        }
                        if ($month > 0) {
                            $str .= "{$month}개월 ";
                        }
                    }
                    if ($days > 0) {
                        $str .= "{$days}일 ";
                    }
                }
                if ($hours > 0 && ($year < 1 || $month < 1 || $days < 1)) {
                    $str .= "{$hours}시간 ";
                }
            }
            if ($minutes > 0 && ($year < 1 || $month < 1 || $days < 1)) {
                $str .= "{$minutes}분";
            }
        }

        if ($year < 1 && $month < 1 && $days < 1 && $hours < 1) {
            $str .= " {$seconds}초";
        }

        return $str;
    }

    /** @var array */
    private $setting = self::SETTING_DEFAULT;

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable(): void {
        $this->saveResource('setting.json');
        $json = file_get_contents($this->getDataFolder() . 'setting.json');
        $json = json_decode($json, true);
        $this->setting = self::SETTING_DEFAULT;
        foreach ($this->setting as $key => $v1) {
            if (isset($json[$key])) {
                if (is_array($v1)) {
                    foreach ($v1 as $k2 => $v2) {
                        if (isset($json[$key][$k2])) {
                            $this->setting[$key][$k2] = $json[$key][$k2];
                        }
                    }
                } else {
                    $this->setting[$key] = $json[$key];
                }
            }
        }

        if (!file_exists($this->getDataFolder() . "license.dat")) {
            $this->getServer()->getLogger()->info(TextFormat::RED . '[ManagementLoader] 라이센스 파일이 없습니다. https://pmmp.me 에서 다운할 수 있습니다.');
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->getLogger()->info(TextFormat::GOLD . '============= [설정 목록] =============');
        foreach ($this->setting as $name => $v) {
            //var_dump($name, $v, $this->setting);
            $enabled = $v['enable'];
            $ver = $v['version'];
            $this->getLogger()->info(TextFormat::GREEN . "{$name}" . ($enabled ? ":" . TextFormat::BLUE . " v{$ver}" : TextFormat::RED . " - OFF"));
        }

        $this->getLogger()->info(TextFormat::GOLD . ' 라이센스 파일을 검증하고 있습니다.');

        $data = file_get_contents($this->getDataFolder() . "license.dat");

        file_put_contents($this->getDataFolder() . 'temp', json_encode([
                'file' => $data,
                'setting' => json_encode($this->setting),
                'api' => $this->getServer()->getApiVersion(),
                'name' => $this->getServer()->getName()
        ]));

        $res = Internet::postURL('https://license.pmmp.me/verify/', [
                'data' => new \CURLFile($this->getDataFolder() . 'temp', "application/json")
        ], 10, [], $err, $resHeaders, $httpCode);

        $msg = '';
        do {
            if (!$res || $httpCode !== 200) {
                var_dump($res);
                $msg = "라이센스 서버가 응답하지 않습니다. 지속되는 경우 관리자에 문의하시기 바랍니다.";
                break;
            }
            $res = json_decode($res, true);
            if ($res === null) {
                $msg = "라이센스 서버가 잘못된 응답을 전송했습니다. 지속되는 경우 관리자에 문의하시기 바랍니다.";
                break;
            }
            try {
                if ($res['success']) {
                    $count = 0;
                    $this->getLogger()->info(TextFormat::BLUE . ' 라이센스 파일이 유효합니다. 플러그인 등록을 시작합니다.');
                    foreach ($res['result'] as $name => $data) {
                        try {
                            //var_dump($data['class']);
                            if ($data['success']) {
                                $this->getLogger()->info(TextFormat::BLUE . " ======= [{$name}] =======");
                                $this->getLogger()->info(TextFormat::GREEN . " 만료 날짜 : " . date("Y-m-d G:i:s", intval($data['expiration'] / 1000)));

                                @mkdir($pluginPath = $this->getServer()->getPluginPath() . $name . DIRECTORY_SEPARATOR);
                                file_put_contents($pluginPath . 'plugin.yml', $data['desc']);
                                $main = yaml_parse(file_get_contents($pluginPath . 'plugin.yml'))['main'];
                                //echo $data['class'][0];
                                //registerClass($data['class'][0]);
                                if (registerClass($data['class']) && class_exists($main, true)) {
                                    self::$plugins[$name] = true;
                                    $this->getServer()->getPluginManager()->registerInterface(new PluginCustomLoader());
                                    $plugin = $this->getServer()->getPluginManager()->loadPlugin($this->getServer()->getPluginPath() . $name);
                                    if ($plugin instanceof Plugin) {
                                        @mkdir($pluginPath . 'resources' . DIRECTORY_SEPARATOR);
                                        foreach ($data['resources'] as $fname => $content) {
                                            file_put_contents(
                                                    $pluginPath . 'resources' . DIRECTORY_SEPARATOR . $fname,
                                                    $content
                                            );
                                        }
                                        ++$count;
                                    } else {
                                        $this->getLogger()->info(TextFormat::RED . " {$name} 플러그인 로딩을 실패했습니다.");
                                        $this->getLogger()->info(TextFormat::RED . " 플러그인 등록을 실패했습니다.");
                                    }
                                } else {
                                    $this->getLogger()->info(TextFormat::RED . " {$name} 플러그인 로딩을 실패했습니다.");
                                    $this->getLogger()->info(TextFormat::RED . " 클래스 등록을 실패했습니다.");
                                }
                            } else {
                                $this->getLogger()->info(TextFormat::RED . " {$name} 플러그인 로딩을 실패했습니다.");
                                $this->getLogger()->info(TextFormat::RED . " {$data['error_message']}");
                            }
                        } catch (\Throwable $e) {
                            $this->getLogger()->info(TextFormat::YELLOW . " {$name} 플러그인을 로딩하던 중 오류가 발생했습니다: {$e->getMessage()}, #L{$e->getLine()}");
                        }

                    }
                    $this->getLogger()->info(TextFormat::BLUE . " 총 {$count}개의 플러그인을 로딩하였습니다.");
                    $this->getLogger()->info(TextFormat::BLUE . " 구매해주셔서 감사합니다.");
                    $this->getServer()->enablePlugins(PluginLoadOrder::STARTUP());
                } else {
                    $msg = "서버 인증을 실패했습니다.";
                    var_dump($res);
                    break;
                }

            } catch (\Throwable $e) {
                $msg = "라이센스 서버가 잘못된 응답을 전송했습니다. 지속되는 경우 관리자에 문의하시기 바랍니다.";
                $msg .= "  " . $e->getMessage();
                break;
            }

        } while (false);

        if ($msg !== '') {
            $this->getLogger()->info(TextFormat::YELLOW . " " . $msg);
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }

}