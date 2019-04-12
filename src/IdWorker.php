<?php

namespace SnowFlake;

interface IdGenerator
{
    /**
     * @return string - biginteger that represents generated id.
     */
    public function nextId();
}

class InvalidSystemClockException extends Exception
{
    // TODO
}

class IdWorker implements IdGenerator
{
    //开始时间,固定一个小于当前时间的毫秒数即可
    const TWEPOC = 1288834974657;

    //机器标识占的位数
    const WORKER_ID_BITS = 5;

    //数据中心标识占的位数
    const DATACENTER_ID_BITS = 5;

    //毫秒内自增数点的位数
    const SEQUENCE_BITS = 12;

    protected $workerId;
    protected $datacenterId;
    protected $sequence;
    protected $lastTimestamp = -1;

    public function __construct($workerId, $datacenterId, $sequence = 0)
    {
        $this->setWorkerId($workerId);
        $this->setDatacenterId($datacenterId);
        $this->sequence = $sequence;
    }

    /**
     * Return the next Snowflake ID.
     * @return string - biginteger that represents generated id.
     * @throws InvalidSystemClockException
     */
    public function nextId()
    {
        $timestamp = $this->getTimestamp();

        //判断时钟是否正常
        if ($timestamp < $this->lastTimestamp) {
            throw new InvalidSystemClockException(sprintf("Clock moved backwards. Refusing to generate id for %d milliseconds", ($this->lastTimestamp - $timestamp)));
        }

        //生成唯一序列
        if ($timestamp == $this->lastTimestamp) {
            $sequence = $this->nextSequence() & $this->sequenceMask();
            // sequence rollover, wait til next millisecond
            if ($sequence == 0) {
                $timestamp = $this->tilNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
            $sequence       = $this->nextSequence();
        }

        $this->lastTimestamp = $timestamp;

        //时间毫秒/数据中心ID/机器ID,要左移的位数
        $t      = floor($timestamp - self::TWEPOC) << $this->timestampLeftShift();
        $dc     = $this->getDatacenterId() << $this->datacenterIdShift();
        $worker = $this->getWorkerId() << $this->workerIdShift();

        //组合4段数据返回: 时间戳.数据标识.工作机器.序列
        return PHP_INT_SIZE === 4 ? $this->mintId32($t, $dc, $worker, $sequence) : $this->mintId64($t, $dc, $worker, $sequence);
    }

    /**
     * Return timestamp in miliseconds
     * @return integer
     */
    public function getTimestamp()
    {
        return floor(microtime(true) * 1000);
    }

    /**
     * Return the Worker Id
     * @return integer
     */
    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     * Return the Datacenter ID
     * @return integer
     */
    public function getDatacenterId()
    {
        return $this->datacenterId;
    }

    /**
     * Get current sequence number
     * @return integer
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * Makes the worker wait til next millisecond.
     * @return integer
     */
    protected function tilNextMillis($lastTimestamp)
    {
        $timestamp = $this->getTimestamp();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->getTimestamp();
        }

        return $timestamp;
    }

    /**
     * Increments and return the sequence.
     * @return integer
     */
    protected function nextSequence()
    {
        return $this->sequence++;
    }

    private function maxWorkerId()
    {
        return -1 ^ (-1 << self::WORKER_ID_BITS);
    }

    private function maxDatacenterId()
    {
        return -1 ^ (-1 << self::DATACENTER_ID_BITS);
    }

    private function workerIdShift()
    {
        return self::SEQUENCE_BITS;
    }

    private function datacenterIdShift()
    {
        return self::SEQUENCE_BITS + self::WORKER_ID_BITS;
    }

    private function timestampLeftShift()
    {
        return self::SEQUENCE_BITS + self::WORKER_ID_BITS + self::DATACENTER_ID_BITS;
    }

    private function sequenceMask()
    {
        return -1 ^ (-1 << self::SEQUENCE_BITS);
    }

    private function mintId32()
    {
        return null;
    }

    private function mintId64($timestamp, $datacenterId, $workerId, $sequence)
    {
        return (string)$timestamp | $datacenterId | $workerId | $sequence;
    }

    /**
     * Set worker Id
     * @param integer $workerId
     * @throws \InvalidArgumentException
     */
    private function setWorkerId($workerId)
    {
        //机器ID范围判断
        if ($workerId > $this->maxWorkerId() || $workerId < 0) {
            throw new InvalidArgumentException(sprintf("worker id can't be greater than %d or less than 0", $this->maxWorkerId()));
        }
        $this->workerId = $workerId;
    }

    /**
     * Set datacenter Id
     * @param integer $datacenterId
     * @throws \InvalidArgumentException
     */
    private function setDatacenterId($datacenterId)
    {
        //数据中心ID范围判断
        if ($datacenterId > $this->maxDatacenterId() || $datacenterId < 0) {
            throw new InvalidArgumentException(sprintf("datacenter id can't be greater than %d or less than 0", $this->maxDatacenterId()));
        }
        $this->datacenterId = $datacenterId;
    }
}