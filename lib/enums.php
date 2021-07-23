<?php

abstract class TCCreatureFlagsExtra {
    const CREATURE_FLAG_EXTRA_INSTANCE_BIND        = 0x00000001;       // creature kill bind instance with killer and killer's group
    const CREATURE_FLAG_EXTRA_CIVILIAN             = 0x00000002;       // not aggro (ignore faction/reputation hostility)
    const CREATURE_FLAG_EXTRA_NO_PARRY             = 0x00000004;       // creature can't parry
    const CREATURE_FLAG_EXTRA_NO_PARRY_HASTEN      = 0x00000008;       // creature can't counter-attack at parry
    const CREATURE_FLAG_EXTRA_NO_BLOCK             = 0x00000010;       // creature can't block
    const CREATURE_FLAG_EXTRA_NO_CRUSHING_BLOWS    = 0x00000020;       // creature can't do crush attacks
    const CREATURE_FLAG_EXTRA_NO_XP                = 0x00000040;       // creature kill does not provide XP
    const CREATURE_FLAG_EXTRA_TRIGGER              = 0x00000080;       // trigger creature
    const CREATURE_FLAG_EXTRA_NO_TAUNT             = 0x00000100;       // creature is immune to taunt auras and 'attack me' effects
    const CREATURE_FLAG_EXTRA_NO_MOVE_FLAGS_UPDATE = 0x00000200;       // creature won't update movement flags
    const CREATURE_FLAG_EXTRA_GHOST_VISIBILITY     = 0x00000400;       // creature will only be visible to dead players
    const CREATURE_FLAG_EXTRA_USE_OFFHAND_ATTACK   = 0x00000800;       // creature will use offhand attacks
    const CREATURE_FLAG_EXTRA_NO_SELL_VENDOR       = 0x00001000;       // players can't sell items to this vendor
    const CREATURE_FLAG_EXTRA_IGNORE_COMBAT        = 0x00002000;       // creature is not allowed to enter combat
    const CREATURE_FLAG_EXTRA_WORLDEVENT           = 0x00004000;       // custom flag for world event creatures (left room for merging)
    const CREATURE_FLAG_EXTRA_GUARD                = 0x00008000;       // Creature is guard
    const CREATURE_FLAG_EXTRA_IGNORE_FEIGN_DEATH   = 0x00010000;       // creature ignores feign death
    const CREATURE_FLAG_EXTRA_NO_CRIT              = 0x00020000;       // creature can't do critical strikes
    const CREATURE_FLAG_EXTRA_NO_SKILL_GAINS       = 0x00040000;       // creature won't increase weapon skills
    const CREATURE_FLAG_EXTRA_OBEYS_TAUNT_DIMINISHING_RETURNS = 0x00080000;       // Taunt is subject to diminishing returns on this creature
    const CREATURE_FLAG_EXTRA_ALL_DIMINISH         = 0x00100000;       // creature is subject to all diminishing returns as players are
    const CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ = 0x00200000;       // creature does not need to take player damage for kill credit
    const CREATURE_FLAG_EXTRA_UNUSED_22            = 0x00400000;
    const CREATURE_FLAG_EXTRA_UNUSED_23            = 0x00800000;
    const CREATURE_FLAG_EXTRA_UNUSED_24            = 0x01000000;
    const CREATURE_FLAG_EXTRA_UNUSED_25            = 0x02000000;
    const CREATURE_FLAG_EXTRA_UNUSED_26            = 0x04000000;
    const CREATURE_FLAG_EXTRA_UNUSED_27            = 0x08000000;
    const CREATURE_FLAG_EXTRA_DUNGEON_BOSS         = 0x10000000;       // creature is a dungeon boss (SET DYNAMICALLY, DO NOT ADD IN DB)
    const CREATURE_FLAG_EXTRA_IGNORE_PATHFINDING   = 0x20000000;       // creature ignore pathfinding
    const CREATURE_FLAG_EXTRA_IMMUNITY_KNOCKBACK   = 0x40000000;       // creature is immune to knockback effects
    const CREATURE_FLAG_EXTRA_UNUSED_31            = 0x80000000;
};

abstract class SunCreatureFlagsExtra {
    const CREATURE_FLAG_EXTRA_INSTANCE_BIND = 0x00000001;       // creature kill bind instance with killer and killer's group
    const CREATURE_FLAG_EXTRA_CIVILIAN = 0x00000002;       // not aggro (ignore faction/reputation hostility)
    const CREATURE_FLAG_EXTRA_NO_PARRY = 0x00000004;       // creature can't parry
    const CREATURE_FLAG_EXTRA_NO_PARRY_RUSH = 0x00000008;       // creature can't parry rush
    const CREATURE_FLAG_EXTRA_NO_BLOCK = 0x00000010;       // creature can't block
    const CREATURE_FLAG_EXTRA_NO_CRUSH = 0x00000020;       // creature can't do crush attacks
    const CREATURE_FLAG_EXTRA_NO_XP_AT_KILL = 0x00000040;       // creature kill not provide XP
    const CREATURE_FLAG_EXTRA_TRIGGER = 0x00000080;       // trigger creature
    const CREATURE_FLAG_EXTRA_DUNGEON_BOSS = 0x00000100;       // creature is a dungeon boss (SET DYNAMICALLY; DO NOT ADD IN DB)
    const CREATURE_FLAG_EXTRA_DUNGEON_HOME = 0x00000200;       // creature will have a home even in dungeon
    const CREATURE_FLAG_EXTRA_TRIGGER_PVP_COMBAT = 0x00000400;       // Will use the PvP combat timers (instead of PvE)
    const CREATURE_FLAG_EXTRA_ZONE_COMBAT = 0x00000800;    // boss-like zone combat in dungeons
    const CREATURE_FLAG_EXTRA_ALL_DIMINISH = 0x00001000;
    const CREATURE_FLAG_EXTRA_NO_SKILLGAIN = 0x2000;
    const CREATURE_FLAG_EXTRA_WORLDEVENT = 0x00004000;       // custom flag for world event creatures (left room for merging)
    const CREATURE_FLAG_EXTRA_NO_SPELL_SLOW = 0x00008000;       // cannot have spell casting slowed down
    const CREATURE_FLAG_EXTRA_NO_TAUNT = 0x00010000;       // cannot be taunted
    const CREATURE_FLAG_EXTRA_NO_CRIT = 0x00020000;       // creature can't do critical strikes
    const CREATURE_FLAG_EXTRA_HOMELESS = 0x00040000;       // consider current position instead of home position for threat area
    const CREATURE_FLAG_EXTRA_GHOST_VISIBILITY = 0x00080000;       // creature will be only visible for dead players
    const CREATURE_FLAG_EXTRA_PERIODIC_RELOC = 0x00100000;       // periodic on place relocation when ooc (use this for static mobs only)
    const CREATURE_FLAG_EXTRA_DUAL_WIELD = 0x00200000;       // can dual wield
    const CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ = 0x00400000;       // creature does not need to take player damage for kill credit
    const CREATURE_FLAG_EXTRA_NO_HEALTH_RESET = 0x00800000;       // creature does not refill its health at reset
    const CREATURE_FLAG_EXTRA_GUARD = 0x01000000;              // Creature is guard
    const CREATURE_FLAG_EXTRA_NO_COMBAT = 0x02000000;         // creature is not allowed to enter combat
    const CREATURE_FLAG_EXTRA_NO_EVADE_TELEPORT = 0x04000000; // No teleport on soft evade for dungeon creatures
};
