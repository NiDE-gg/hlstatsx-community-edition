/**
 * HLstatsX Community Edition - SourceMod plugin to generate advanced weapon logging
 * http://www.hlxcommunity.com
 * Copyright (C) 2009 Nicholas Hastings (psychonic)
 * Copyright (C) 2007-2008 TTS Oetzel & Goerz GmbH
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#pragma semicolon 1
#pragma newdecls required
 
#include <sourcemod>
#include <sdktools>

#define NAME "SuperLogs: DOD:S"
#define VERSION "1.1.4"

#define MAX_LOG_WEAPONS    27
#define IGNORE_SHOTS_START	20
#define MAX_WEAPON_LEN 16


int g_weapon_stats[MAXPLAYERS+1][MAX_LOG_WEAPONS][15];
char g_weapon_list[MAX_LOG_WEAPONS][MAX_WEAPON_LEN] = {
									"amerknife",
									"spade",
									"colt",
									"p38",
									"c96",
									"garand",
									"m1carbine",
									"k98",
									"spring",
									"k98_scoped",
									"thompson",
									"mp40",
									"mp44",
									"bar",
									"30cal",
									"mg42",
									"bazooka",
									"pschreck",
									"frag_us",
									"frag_ger",
									"",
									"",
									"smoke_us",
									"smoke_ger",
									"riflegren_us",
									"riflegren_ger",
									"dod_bomb_target"
								};

								
Handle g_cvar_wstats = INVALID_HANDLE;
Handle g_cvar_headshots = INVALID_HANDLE;
Handle g_cvar_locations = INVALID_HANDLE;
Handle g_cvar_ktraj = INVALID_HANDLE;
Handle g_cvar_version = INVALID_HANDLE;

bool g_logwstats = true;
bool g_logheadshots = true;
bool g_loglocations = true;
bool g_logktraj = true;

#include <loghelper>
#include <wstatshelper>


public Plugin myinfo = {
	name = NAME,
	author = "psychonic",
	description = "Advanced logging for DOD:S. Generates auxilary logging for use with log parsers such as HLstatsX and Psychostats",
	version = VERSION,
	url = "http://www.hlxcommunity.com"
};

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	char game_description[64];
	GetGameDescription(game_description, sizeof(game_description), true);
	if (StrContains(game_description, "Day of Defeat", false) == -1)
	{
		char game_folder[64];
		GetGameFolderName(game_folder, sizeof(game_folder));
		if (strncmp(game_folder, "dod", 3, false) != 0)
		{
			strcopy(error, err_max, "This plugin is only supported on DOD:S");
			return APLRes_Failure;
		}
	}
	return APLRes_Success;
}


public void OnPluginStart()
{
	CreatePopulateWeaponTrie();
	
	g_cvar_wstats = CreateConVar("superlogs_wstats", "1", "Enable logging of weapon stats (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_headshots = CreateConVar("superlogs_headshots", "1", "Enable logging of headshot player action (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_locations = CreateConVar("superlogs_locations", "1", "Enable logging of location on player death (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_ktraj = CreateConVar("superlogs_ktraj", "0", "Enable Psychostats \"KTRAJ\" logging (default off)", 0, true, 0.0, true, 1.0);
	HookConVarChange(g_cvar_wstats, OnCvarWstatsChange);
	HookConVarChange(g_cvar_headshots, OnCvarHeadshotsChange);
	HookConVarChange(g_cvar_locations, OnCvarLocationsChange);
	HookConVarChange(g_cvar_ktraj, OnCvarKtrajChange);
	g_cvar_version = CreateConVar("superlogs_dods_version", VERSION, NAME, FCVAR_SPONLY|FCVAR_REPLICATED|FCVAR_NOTIFY);
		
	hook_wstats();
	HookEvent("player_hurt", Event_PlayerHurt);
	HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
	HookEvent("player_death", Event_PlayerDeath);
		
	CreateTimer(1.0, LogMap);

	GetTeams();

	// Everything above is hooked as if all options were on: apply the real cvar values
	// (they keep theirs when the plugin is reloaded, and superlogs_ktraj defaults to 0)
	OnCvarWstatsChange(g_cvar_wstats, "", "");
	OnCvarHeadshotsChange(g_cvar_headshots, "", "");
	OnCvarLocationsChange(g_cvar_locations, "", "");
	OnCvarKtrajChange(g_cvar_ktraj, "", "");
}


public void OnMapStart()
{
	GetTeams();
}


public void OnConfigsExecuted()
{
	char version[255];
	GetConVarString(g_cvar_version, version, sizeof(version));
	SetConVarString(g_cvar_version, version);
}


void hook_wstats()
{
	HookEvent("dod_stats_weapon_attack", Event_PlayerShoot);
	HookEvent("player_spawn", Event_PlayerSpawn);
	HookEvent("dod_round_win", Event_RoundEnd, EventHookMode_PostNoCopy);
	HookEvent("player_disconnect", Event_PlayerDisconnect, EventHookMode_Pre);
}

void unhook_wstats()
{
	UnhookEvent("dod_stats_weapon_attack", Event_PlayerShoot);
	UnhookEvent("player_spawn", Event_PlayerSpawn);
	UnhookEvent("dod_round_win", Event_RoundEnd, EventHookMode_PostNoCopy);
	UnhookEvent("player_disconnect", Event_PlayerDisconnect, EventHookMode_Pre);
}

public void OnClientPutInServer(int client)
{
	reset_player_stats(client);
}


public void Event_PlayerShoot(Handle event, const char[] name, bool dontBroadcast)
{
	// "attacker"      "short"
    // "weapon"        "byte"
	
	int attacker   = GetClientOfUserId(GetEventInt(event, "attacker"));
	if (attacker > 0)
	{
		int weapon_index = GetEventInt(event, "weapon") - 1;
		switch (weapon_index)
		{
			case 28, 29:
				weapon_index = -1;
			case 30:
				weapon_index = 5;
			case 32:
				weapon_index = 8;
			case 33:
				weapon_index = 9;
			case 34:
				weapon_index = 14;
			case 35:
				weapon_index = 15;
			case 37:
				weapon_index = 12;
		}
		if (weapon_index > -1 && weapon_index < IGNORE_SHOTS_START)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_SHOTS]++;
		}
	}
}


public void Event_PlayerHurt(Handle event, const char[] name, bool dontBroadcast)
{
	// "userid"        "short"         // user ID who was hurt
	// "attacker"      "short"         // user ID who attacked
	// "weapon"        "string"        // weapon name attacker used
	// "health"        "byte"          // health remaining
	// "damage"        "byte"          // how much damage in this attack
	// "hitgroup"      "byte"          // what hitgroup was hit
	
	int attacker = GetClientOfUserId(GetEventInt(event, "attacker"));
	int hitgroup = GetEventInt(event, "hitgroup");
	bool headshot = (GetEventInt(event, "health") <= 0 && hitgroup == HITGROUP_HEAD);
	
	if (g_logwstats && attacker > 0)
	{
		char weapon[MAX_WEAPON_LEN];
		GetEventString(event, "weapon", weapon, sizeof(weapon));
		int weapon_index = get_weapon_index(weapon);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_HITS]++;
			g_weapon_stats[attacker][weapon_index][LOG_HIT_DAMAGE] += GetEventInt(event, "damage");
			if (hitgroup < 8)
			{
				g_weapon_stats[attacker][weapon_index][hitgroup + LOG_HIT_OFFSET]++;
			}
			if (headshot)
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_HEADSHOTS]++;
			}
		}
	}
	if (g_logheadshots && headshot)
	{
		LogPlayerEvent(attacker, "triggered", "headshot");
	}
}

public Action Event_PlayerDeathPre(Handle event, const char[] name, bool dontBroadcast)
{
	LogKillLoc(GetClientOfUserId(GetEventInt(event, "attacker")), GetClientOfUserId(GetEventInt(event, "userid")));
	
	return Plugin_Continue;
}

public void Event_PlayerDeath(Handle event, const char[] name, bool dontBroadcast)
{
	// this extents the original player_death
	// "userid"        "short"         // user ID who died
	// "attacker"      "short"         // user ID who killed
	// "weapon"        "string"        // weapon name killed used

	int victim   = GetClientOfUserId(GetEventInt(event, "userid"));
	int attacker = GetClientOfUserId(GetEventInt(event, "attacker"));
	char weapon[MAX_WEAPON_LEN];
	GetEventString(event, "weapon", weapon, sizeof(weapon));

	if (g_logwstats && victim > 0 && attacker > 0)
	{
		int weapon_index = get_weapon_index(weapon);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_KILLS]++;
			g_weapon_stats[victim][weapon_index][LOG_HIT_DEATHS]++;
			if (GetClientTeam(attacker) == GetClientTeam(victim))
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_TEAMKILLS]++;
			}
			dump_player_stats(victim);
		}
	}
	if (g_logktraj)
	{
		LogPSKillTraj(attacker, victim, weapon);
	}
}

public void Event_PlayerSpawn(Handle event, const char[] name, bool dontBroadcast)
{
	// "userid"        "short"         // user ID on server          

	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	if (client > 0)
	{
		reset_player_stats(client);
	}
}

public void Event_RoundEnd(Handle event, const char[] name, bool dontBroadcast)
{
	WstatsDumpAll();
}

public Action Event_PlayerDisconnect(Handle event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	OnPlayerDisconnect(client);
	return Plugin_Continue;
}


public Action LogMap(Handle timer)
{
	// Called 1 second after OnPluginStart since srcds does not log the first map loaded. Idea from Stormtrooper's "mapfix.sp" for psychostats
	LogMapLoad();
	return Plugin_Stop;
}


public void OnCvarWstatsChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logwstats;
	g_logwstats = GetConVarBool(g_cvar_wstats);
	
	if (old_value != g_logwstats)
	{
		if (g_logwstats)
		{
			hook_wstats();
			if (!g_logheadshots)
			{
				HookEvent("player_hurt", Event_PlayerHurt);
			}
			if (!g_logktraj)
			{
				HookEvent("player_death", Event_PlayerDeath);
			}
		}
		else
		{
			unhook_wstats();
			if (!g_logheadshots)
			{
				UnhookEvent("player_hurt", Event_PlayerHurt);
			}
			if (!g_logktraj)
			{
				UnhookEvent("player_death", Event_PlayerDeath);
			}
		}
	}
}


public void OnCvarHeadshotsChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logheadshots;
	g_logheadshots = GetConVarBool(g_cvar_headshots);
	
	if (old_value != g_logheadshots)
	{
		if (g_logheadshots && !g_logwstats)
		{
			HookEvent("player_hurt", Event_PlayerHurt);
		}
		else if (!g_logwstats)
		{
			UnhookEvent("player_hurt", Event_PlayerHurt);
		}
	}
}

public void OnCvarLocationsChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_loglocations;
	g_loglocations = GetConVarBool(g_cvar_locations);
	
	if (old_value != g_loglocations)
	{
		if (g_loglocations)
		{
			HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
		else
		{
			UnhookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
	}
}

public void OnCvarKtrajChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logktraj;
	g_logktraj = GetConVarBool(g_cvar_ktraj);
	
	if (old_value != g_logktraj)
	{
		if (g_logktraj && !g_logwstats)
		{
			HookEvent("player_death", Event_PlayerDeath);
		}
		else if (!g_logwstats)
		{
			UnhookEvent("player_death", Event_PlayerDeath);
		}
	}
}