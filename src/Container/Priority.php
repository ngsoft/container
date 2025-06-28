<?php

namespace NGSOFT\Container;

enum Priority: int
{
    case HIGH   = 128;
    case MEDIUM = 64;
    case LOW    = 32;
}
