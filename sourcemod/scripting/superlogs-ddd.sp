/**
 * HLstatsX Community Edition - SourceMod plugin to generate advanced weapon logging
 * http://www.hlxcommunity.com
 * Copyright (C) 2009-2012 Nicholas Hastings (psychonic)
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
#include <sdkhooks>

#define NAME "SuperLogs: Dino D-Day"
#define VERSION "1.1.2"

#define MAX_LOG_WEAPONS 47
#define MAX_WEAPON_LEN 12
#define PREFIX_LEN 7

// Some DDD Things
#define WEAPON_MG42	18
#define WEAPON_TREX	37
#define PLAYERCLASS_TREX	8
#define PLAYERCLASS_DILOPHOSAURUS	5
#define CUSTOMKILL_GOAT	5
#define GOAT_BITE	19
#define DDD_TEAM_ALLIES	2
#define DDD_TEAM_AXIS	3

int g_weapon_stats[MAXPLAYERS+1][MAX_LOG_WEAPONS][15];
char g_weapon_list[MAX_LOG_WEAPONS][MAX_WEAPON_LEN] = { 
									"",
									"mp40",
									"thompson",
									"shotgun",
									"",
									"pistol",
									"",
									"",
									"garand",
									"bar",
									"luger",
									"",
									"piat",
									"",
									"mosin",
									"k98",
									"",
									"",
									"mg42",
									"",
									"k98sniper",
									"",
									"",
									"",
									"flechette",
									"",
									"",
									"",
									"",
									"mp44",
									"",
									"",
									"sten",
									"p38",
									"nagant",
									"",
									"",
									"trex",
									"",
									"",
									"trigger",
									"stygimoloch",
									"",
									"",
									"",
									"carbine",
									"greasegun"
								};

int g_iNextHitgroup[MAXPLAYERS+1];
bool g_DiloGoatBite[MAXPLAYERS+1];

int g_LastClass[MAXPLAYERS+1] = { -1, ... };
int g_LastTeam[MAXPLAYERS+1] = { -1, ... };

bool g_bLate;
bool g_bIgnoreLog;

void PauseLogging() { g_bIgnoreLog = true; }
void ResumeLogging() { g_bIgnoreLog = false; }

#include <loghelper>
#include <wstatshelper>


public Plugin myinfo = {
	name = NAME,
	author = "psychonic and FeuerSturm",
	description = "Advanced logging for Dino D-Day. Generates auxilary logging for use with log parsers such as HLstatsX and Psychostats",
	version = VERSION,
	url = "http://www.hlxce.com"
};

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	char szGameDir[64];
	GetGameFolderName( szGameDir, sizeof(szGameDir) );
	if ( !!strcmp( szGameDir, "dinodday" ) )
	{
		strcopy( error, err_max, "This plugin is only supported on Dino D-Day" );
		return APLRes_Failure;
	}
	
	g_bLate = late;
	
	return APLRes_Success;
}

public void OnPluginStart()
{
	CreatePopulateWeaponTrie();
	Handle hVersion = CreateConVar( "superlogs_dinodday_version", VERSION, NAME, FCVAR_NOTIFY|FCVAR_DONTRECORD );
	SetConVarString(hVersion, VERSION, false, true);
	
	HookEvent( "player_death",       Event_PlayerDeathPre,       EventHookMode_Pre        );
	HookEvent( "player_death",       Event_PlayerDeath,          EventHookMode_Post       );
	HookEvent( "player_spawn",       Event_PlayerSpawn,          EventHookMode_Post       );
	HookEvent( "update_roundscore",  Event_RoundEnd,             EventHookMode_Post       );
	HookEvent( "player_changeclass", Event_PlayerChangeClassPre, EventHookMode_Pre        );
	HookEvent( "player_changeclass", Event_PlayerChangeClass,    EventHookMode_Post       );
	HookEvent( "teamplay_point_captured", Event_PointCaptured,    EventHookMode_Post       );
	
	AddGameLogHook( OnGameLog );
	AddTempEntHook( "Shotgun Shot", OnFireBullets );
}

public void OnAllPluginsLoaded()
{
	for ( int i = 1; i <= MaxClients; i++ )
	{
		if ( IsClientInGame( i ) )
		{
			OnClientPutInServer( i );
		}
	}
}

public void OnMapStart()
{
	static bool bLoggedMap = false;
	if( g_bLate && !bLoggedMap )
	{
		LogMapLoad();
	}
	
	bLoggedMap = true;
	
	GetTeams();
}

public void OnClientPutInServer(int client)
{
	SDKHook( client, SDKHook_TraceAttackPost, OnTraceAttack );
	SDKHook( client, SDKHook_OnTakeDamagePost, OnTakeDamage );
	reset_player_stats( client );
	
	g_LastTeam[client] = -1;
	g_LastClass[client] = -1;
	g_DiloGoatBite[client] = false;
}

public Action OnFireBullets(const char[] szName, const int[] clients, int clientCount, float flDelay)
{
	int client = TE_ReadNum( "m_iPlayer" ) + 1;
	int weapon_index = TE_ReadNum( "m_iWeaponID" );
	
	if( client < 1 || client > MaxClients || !IsClientInGame( client ) )
		return Plugin_Continue;
	
	if( weapon_index >= 0 && weapon_index < MAX_LOG_WEAPONS )
	{
		if( GetEntProp(client, Prop_Send, "m_iPlayerClass") == PLAYERCLASS_TREX && weapon_index == WEAPON_MG42 )
		{
			weapon_index = WEAPON_TREX;
		}
		g_weapon_stats[client][weapon_index][LOG_HIT_SHOTS]++;
	}
	
	return Plugin_Continue;
}

public void OnTraceAttack(int victim, int attacker, int inflictor, float damage, int damagetype, int ammotype, int hitbox, int hitgroup)
{
	if ( hitgroup > 0 && attacker > 0 && attacker <= MaxClients && victim > 0 && victim <= MaxClients )
	{
		g_iNextHitgroup[victim] = hitgroup;
	}
}

public void OnTakeDamage(int victim, int attacker, int inflictor, float damage, int damagetype)
{	
	if ( attacker > 0 && attacker <= MaxClients && victim > 0 && victim <= MaxClients )
	{
		char weapon[MAX_WEAPON_LEN + PREFIX_LEN];
		GetClientWeapon( attacker, weapon, sizeof(weapon) );
		
		int weapon_index = get_weapon_index(weapon[PREFIX_LEN]);
		int hitgroup = g_iNextHitgroup[victim];
		
		bool headshot = ( !IsPlayerAlive( victim ) && hitgroup == HITGROUP_HEAD );
		if ( weapon_index > -1 )
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_HITS]++;
			g_weapon_stats[attacker][weapon_index][LOG_HIT_DAMAGE] += RoundToNearest( damage );
			// body parts are hitgroups 0-7; 10 is gear, which has no column
			if ( hitgroup < 8 )
			{
				g_weapon_stats[attacker][weapon_index][hitgroup + LOG_HIT_OFFSET]++;
			}
			if ( headshot )
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_HEADSHOTS]++;
				LogPlayerEvent( attacker, "triggered", "headshot" );
			}
		}

		g_iNextHitgroup[victim] = 0;
	}
}

public void OnEntityCreated(int entity, const char[] classname)
{
	if(entity > MaxClients && IsValidEdict(entity) && StrEqual(classname, "npc_goat", true))
	{
		SDKHook(entity, SDKHook_ThinkPost, OnGoatThinkPost);
	}
}

public void OnGoatThinkPost(int npc_goat)
{
	if(npc_goat > MaxClients && IsValidEdict(npc_goat))
	{
		int client = GetEntPropEnt(npc_goat, Prop_Send, "moveparent");
		if(client >= 1 && client <= MaxClients && IsClientInGame(client) && IsPlayerAlive(client) && GetClientTeam(client) == DDD_TEAM_AXIS && GetEntProp(client, Prop_Send, "m_iPlayerClass") == PLAYERCLASS_DILOPHOSAURUS)
		{
			if(GetEntProp(client, Prop_Send, "m_nSequence") == GOAT_BITE)
			{
				g_DiloGoatBite[client] = true;
			}
		}
	}	
}

public Action OnGameLog(const char[] szMessage)
{
	if( g_bIgnoreLog )
		return Plugin_Handled;
	
	return Plugin_Continue;
}

public Action Event_PointCaptured(Handle event, const char[] name, bool dontBroadcast)
{
	int client;
	char cappers[256];
	GetEventString(event, "cappers", cappers, sizeof(cappers));
	for (int i = 0 ; i < strlen(cappers); i++)
	{
		client = cappers[i];
		if(client > 0 && client <= MaxClients && IsClientInGame(client))
		{
			LogPlayerEvent(client, "triggered", "point_captured");
		}
	}
	
	return Plugin_Continue;
}

public Action Event_PlayerChangeClassPre(Handle event, const char[] name, bool dontBroadcast)
{
	PauseLogging();
	
	return Plugin_Continue;
}

public void Event_PlayerChangeClass(Handle event, const char[] name, bool dontBroadcast)
{
	ResumeLogging();
}

public Action Event_PlayerDeathPre(Handle event, const char[] name, bool dontBroadcast)
{
	PauseLogging();
	
	int customkill = GetEventInt(event, "customkill");
	if( customkill == CUSTOMKILL_GOAT )
	{
		int attacker = GetClientOfUserId( GetEventInt( event, "attacker" ) );
		int victim = GetClientOfUserId( GetEventInt( event, "userid" ) );
		if(attacker == victim && g_DiloGoatBite[attacker])
		{
			SetEventBroadcast(event, true);
			return Plugin_Continue;
		}
	}
	
	return Plugin_Continue;
}

public void Event_PlayerDeath(Handle event, const char[] name, bool dontBroadcast)
{
	ResumeLogging();
	
	int victim = GetClientOfUserId( GetEventInt( event, "userid" ) );
	
	if ( victim > 0 )
	{
		int attacker = GetClientOfUserId( GetEventInt( event, "attacker" ) );

		char weapon[32];
		GetEventString( event, "weapon", weapon, sizeof(weapon) );
		if( attacker > 0 && attacker != victim )
		{
			int weapon_index = get_weapon_index( weapon );
			if ( weapon_index > -1 )
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_KILLS]++;		
				g_weapon_stats[victim][weapon_index][LOG_HIT_DEATHS]++;
				if ( GetClientTeam( attacker ) == GetClientTeam( victim ) )
				{
					g_weapon_stats[attacker][weapon_index][LOG_HIT_TEAMKILLS]++;
				}	
			}
			
			LogKill( attacker, victim, weapon, true );
			
			int dominated = GetEventInt(event, "dominated");
			int revenge = GetEventInt(event, "revenge");
			if(dominated == 1)
			{
				LogPlyrPlyrEvent(attacker, victim, "triggered", "dominated");
			}
			else if(revenge == 1)
			{
				LogPlyrPlyrEvent(attacker, victim, "triggered", "revenge");
			}

			int assisteruid = GetEventInt(event, "assister");
			if(assisteruid != -1)
			{
				int assister = GetClientOfUserId(assisteruid);
				if(assister > 0 && attacker != assister)
				{
					LogPlayerEvent(assister, "triggered", "assister");
					if(GetEventInt(event, "assister_revenge") == 1)
					{
						LogPlyrPlyrEvent(assister, victim, "triggered", "assister_revenge");
					}
					else if(GetEventInt(event, "assister_dominated") == 1)
					{
						LogPlyrPlyrEvent(assister, victim, "triggered", "assister_dominated");
					}
				}
			}
			
		}
		else
		{
			int customkill = GetEventInt(event, "customkill");
			if( customkill == CUSTOMKILL_GOAT )
			{
				if(g_DiloGoatBite[attacker])
				{
					g_DiloGoatBite[attacker] = false;
					return;
				}
				LogPlayerEvent(attacker, "triggered", "kill_goat");
				return;
			}
			
			LogPlayerEvent(victim, "committed suicide with", weapon);
		}
		
		dump_player_stats( victim );
	}
}

public void Event_PlayerSpawn(Handle event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId( GetEventInt( event, "userid" ) );
	if( client == 0 || !IsClientInGame(client) )
		return;
	
	reset_player_stats( client );
	
	int currentTeam = GetClientTeam( client );
	if( currentTeam != DDD_TEAM_ALLIES && currentTeam != DDD_TEAM_AXIS )
		return;
	
	int currentClass = GetEntProp( client, Prop_Send, "m_iPlayerClass" );
	if( g_LastClass[client] != currentClass || g_LastTeam[client] != currentTeam )
	{
		char szRoleString[32];
		if( currentTeam == DDD_TEAM_ALLIES )
			Format( szRoleString, sizeof(szRoleString), "#class_blue_class%d", currentClass+1 );
		else // == DDD_TEAM_AXIS
			Format( szRoleString, sizeof(szRoleString), "#class_red_class%d", currentClass+1 );
		
		LogRoleChange( client, szRoleString );
		g_LastTeam[client] = currentTeam;
		g_LastClass[client] = currentClass;
	}
	g_DiloGoatBite[client] = false;
}

public void Event_RoundEnd(Handle event, const char[] name, bool dontBroadcast)
{
	int winner = GetEventInt( event, "team_that_won" );
	if( winner == DDD_TEAM_ALLIES || winner == DDD_TEAM_AXIS )
	{
		char round_win[32];
		Format(round_win, sizeof(round_win), "%s", winner == DDD_TEAM_ALLIES ? "roundwin_allies" : "roundwin_axis");
		LogTeamEvent(winner, "triggered", round_win);
		WstatsDumpAll();
	}
}

public void OnClientDisconnect(int client)
{
	OnPlayerDisconnect( client );
}