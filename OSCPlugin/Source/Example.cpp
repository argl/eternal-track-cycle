//‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹‹
//›                                                                         ﬁ
//› Module: Internals Example Source File                                   ﬁ
//›                                                                         ﬁ
//› Description: Declarations for the Internals Example Plugin              ﬁ
//›                                                                         ﬁ
//›                                                                         ﬁ
//› This source code module, and all information, data, and algorithms      ﬁ
//› associated with it, are part of CUBE technology (tm).                   ﬁ
//›                 PROPRIETARY AND CONFIDENTIAL                            ﬁ
//› Copyright (c) 1996-2007 Image Space Incorporated.  All rights reserved. ﬁ
//›                                                                         ﬁ
//›                                                                         ﬁ
//› Change history:                                                         ﬁ
//›   tag.2005.11.30: created                                               ﬁ
//›                                                                         ﬁ
//ﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂﬂ

#include "Example.hpp"          // corresponding header file
#include <math.h>               // for atan2, sqrt
#include <stdio.h>              // for sample output
#include "IniReader.h"
#include "IniWriter.h"


// plugin information
unsigned g_uPluginID          = 0;
char     g_szPluginName[]     = "OSCTelemetry Plugin";
unsigned g_uPluginVersion     = 001;
unsigned g_uPluginObjectCount = 1;
InternalsPluginInfo g_PluginInfo;

// interface to plugin information
extern "C" __declspec(dllexport)
const char* __cdecl GetPluginName() { return g_szPluginName; }

extern "C" __declspec(dllexport)
unsigned __cdecl GetPluginVersion() { return g_uPluginVersion; }

extern "C" __declspec(dllexport)
unsigned __cdecl GetPluginObjectCount() { return g_uPluginObjectCount; }

// get the plugin-info object used to create the plugin.
extern "C" __declspec(dllexport)
PluginObjectInfo* __cdecl GetPluginObjectInfo( const unsigned uIndex )
{
  switch(uIndex)
  {
    case 0:
      return  &g_PluginInfo;
    default:
      return 0;
  }
}


// InternalsPluginInfo class

InternalsPluginInfo::InternalsPluginInfo()
{
  // put together a name for this plugin
  sprintf( m_szFullName, "%s - %s", g_szPluginName, InternalsPluginInfo::GetName() );
}

const char*    InternalsPluginInfo::GetName()     const { return ExampleInternalsPlugin::GetName(); }
const char*    InternalsPluginInfo::GetFullName() const { return m_szFullName; }
const char*    InternalsPluginInfo::GetDesc()     const { return "Send Telemetry via Open Sound Control"; }
const unsigned InternalsPluginInfo::GetType()     const { return ExampleInternalsPlugin::GetType(); }
const char*    InternalsPluginInfo::GetSubType()  const { return ExampleInternalsPlugin::GetSubType(); }
const unsigned InternalsPluginInfo::GetVersion()  const { return ExampleInternalsPlugin::GetVersion(); }
void*          InternalsPluginInfo::Create()      const { return new ExampleInternalsPlugin(); }


// InternalsPlugin class

const char ExampleInternalsPlugin::m_szName[] = "OSCTelemetry";
const char ExampleInternalsPlugin::m_szSubType[] = "x";
const unsigned ExampleInternalsPlugin::m_uID = 1;
const unsigned ExampleInternalsPlugin::m_uVersion = 3;  // set to 3 for InternalsPluginV3 functionality and added graphical and vehicle info


PluginObjectInfo *ExampleInternalsPlugin::GetInfo()
{
  return &g_PluginInfo;
}


void ExampleInternalsPlugin::WriteToAllExampleOutputFiles( const char * const openStr, const char * const msg )
{

  FILE *fo;

  fo = fopen( "OSCDebug.txt", openStr );
  if( fo != NULL )
  {
    fprintf( fo, "%s\n", msg );
    fclose( fo );
  }
}


void ExampleInternalsPlugin::Startup()
{
  // Open ports, read configs, whatever you need to do.  For now, I'll just clear out the
  // example output data files.
  WriteToAllExampleOutputFiles( "w", "-STARTUP-" );
  // get ini file values here

  // default HW control enabled to true
  mEnabled = true;
  m_in_car = false;

  m_tel_base_timer = 0.0f;
  m_tel_damage_timer = 0.0f;
  m_tel_space_timer = 0.0f;
  m_tel_wheel_timer = 0.0f;
  m_tel_user_input_timer = 0.0f;
  m_scoring_global_timer = 0.0f;
  m_scoring_driver_counter = 0;
  m_scoring_space_counter = 0;

  m_send_telemetry = false;
  m_send_scoring = false;
}


void ExampleInternalsPlugin::Shutdown()
{
  WriteToAllExampleOutputFiles( "a", "-SHUTDOWN-" );
}


void ExampleInternalsPlugin::StartSession()
{
  WriteToAllExampleOutputFiles( "a", "--STARTSESSION--" );
  m_packetStream = new osc::OutboundPacketStream( m_buffer, IP_MTU_SIZE );
  if (m_packetStream == NULL) {
	  WriteToAllExampleOutputFiles( "w", "--ERROR creating packet stream" );
  }

  CIniReader* ini_reader = new CIniReader(".\\OSC.ini");

  char* host = ini_reader->ReadString("Network", "Host", NULL);
  if (host[0] == 0) {
    CIniWriter* ini_writer = new CIniWriter(".\\OSC.ini");
    ini_writer->WriteString("Network", "Host", "127.0.0.1");
    host = ini_reader->ReadString("Network", "Host", "127.0.0.1");
    delete ini_writer;
  }

  int port = ini_reader->ReadInteger("Network", "Port", 0);
  if (port < 1) {
    CIniWriter* ini_writer = new CIniWriter(".\\OSC.ini");
    ini_writer->WriteInteger("Network", "Port", 6767);
    port = ini_reader->ReadInteger("Network", "Port", 6767);
    delete ini_writer;
  }

  {
    CIniWriter* ini_writer = new CIniWriter(".\\OSC.ini");
    m_send_telemetry = ini_reader->ReadBoolean("Network", "Telemetry", 0);
    ini_writer->WriteBoolean("Network", "Telemetry", m_send_telemetry);

    m_send_scoring = ini_reader->ReadBoolean("Network", "Scoring", 0);
    ini_writer->WriteBoolean("Network", "Scoring", m_send_scoring);
	  delete ini_writer;
  }
  delete ini_reader;

  m_socket = new UdpTransmitSocket( IpEndpointName( host, port ) );
  
  if (m_socket == NULL) {
	  WriteToAllExampleOutputFiles( "w", "--ERROR creating udp transmit socket" );
  }
  if (m_packetStream && m_socket) {
	m_packetStream->Clear();
	*m_packetStream << osc::BeginMessage( "/in_session" ) << 1 << osc::EndMessage;
	m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
  }
}


void ExampleInternalsPlugin::EndSession()
{
  WriteToAllExampleOutputFiles( "a", "--ENDSESSION--" );
  if (m_packetStream && m_socket) {
	m_packetStream->Clear();
	*m_packetStream << osc::BeginMessage( "/in_session" ) << 0 << osc::EndMessage;
	m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
  }
  if (m_packetStream) { delete m_packetStream; m_packetStream = NULL; }
  if (m_socket) { delete m_socket; m_socket = NULL; }
}


void ExampleInternalsPlugin::EnterRealtime()
{
  // start up timer every time we enter realtime
  mET = 0.0f;
  m_in_car = true;
  WriteToAllExampleOutputFiles( "a", "---ENTERREALTIME---" );
  if (m_packetStream && m_socket) {
	m_packetStream->Clear();
	*m_packetStream << osc::BeginMessage( "/in_car" ) << 1 << osc::EndMessage;
	m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
  }

}


void ExampleInternalsPlugin::ExitRealtime()
{
  WriteToAllExampleOutputFiles( "a", "---EXITREALTIME---" );
  m_in_car = false;
  if (m_packetStream && m_socket) {
	m_packetStream->Clear();
	*m_packetStream << osc::BeginMessage( "/in_car" ) << 0 << osc::EndMessage;
	m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
  }
}


void ExampleInternalsPlugin::UpdateTelemetry( const TelemInfoV2 &info )
{
  // bail if we are not in car or no net
  if (!m_send_telemetry || !m_socket || !m_in_car || !m_packetStream) {
    return;
  }

  m_tel_base_timer += info.mDeltaTime;
  m_tel_damage_timer += info.mDeltaTime;
  m_tel_space_timer += info.mDeltaTime;
  m_tel_wheel_timer += info.mDeltaTime;
  m_tel_user_input_timer += info.mDeltaTime;

  // send /telemetry/base at 30 hz
  if (m_tel_base_timer > 0.0333f) {
    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/telemetry/base" );

	*m_packetStream << m_tel_base_timer << info.mLapNumber << info.mLapStartET << info.mVehicleName << info.mTrackName;
    *m_packetStream << (int)info.mGear << info.mEngineRPM << info.mEngineWaterTemp << info.mEngineOilTemp;
    *m_packetStream << info.mClutchRPM << info.mFuel << info.mEngineMaxRPM;
    // Compute some auxiliary info based on the above
    TelemVect3 forwardVector = { -info.mOriX.z, -info.mOriY.z, -info.mOriZ.z };
    TelemVect3    leftVector = {  info.mOriX.x,  info.mOriY.x,  info.mOriZ.x };
    // These are normalized vectors, and remember that our world Y coordinate is up.  So you can
    // determine the current pitch and roll (w.r.t. the world x-z plane) as follows:
    const float pitch = atan2f( forwardVector.y, sqrtf( ( forwardVector.x * forwardVector.x ) + ( forwardVector.z * forwardVector.z ) ) );
    const float  roll = atan2f(    leftVector.y, sqrtf( (    leftVector.x *    leftVector.x ) + (    leftVector.z *    leftVector.z ) ) );
    const float radsToDeg = 57.296f;
    const float metersPerSec = sqrtf( ( info.mLocalVel.x * info.mLocalVel.x ) + ( info.mLocalVel.y * info.mLocalVel.y ) + ( info.mLocalVel.z * info.mLocalVel.z ) );
    *m_packetStream << "_aux";
    *m_packetStream << (pitch * radsToDeg) << (roll * radsToDeg);
    *m_packetStream << (metersPerSec * 3.6f) << (metersPerSec * 2.237f);
    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
    m_tel_base_timer = 0.0f;
  }
  
  if (m_tel_damage_timer > 0.2f) {
    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/telemetry/damage" );

    *m_packetStream << (int)info.mScheduledStops << (int)info.mOverheating << (int)info.mDetached;
    *m_packetStream << info.mDentSeverity[0] << info.mDentSeverity[1] << info.mDentSeverity[2] << info.mDentSeverity[3];
    *m_packetStream << info.mDentSeverity[4] << info.mDentSeverity[5] << info.mDentSeverity[6] << info.mDentSeverity[7];
    *m_packetStream << info.mLastImpactET << info.mLastImpactMagnitude;
    *m_packetStream << info.mLastImpactPos.x << info.mLastImpactPos.y << info.mLastImpactPos.z;

    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
    m_tel_damage_timer = 0.0f;
  }

  // send telemetry space at about 0.5 hz
  if (m_tel_space_timer > 0.5) {
    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/telemetry/space" );

    *m_packetStream << info.mPos.x << info.mPos.y << info.mPos.z;
    *m_packetStream << info.mLocalVel.x << info.mLocalVel.y << info.mLocalVel.z;
    *m_packetStream << info.mLocalAccel.x << info.mLocalAccel.y << info.mLocalAccel.z;
    *m_packetStream << info.mOriX.x << info.mOriX.y << info.mOriX.z;
    *m_packetStream << info.mOriY.x << info.mOriY.y << info.mOriY.z;
    *m_packetStream << info.mOriZ.x << info.mOriZ.y << info.mOriZ.z;
    *m_packetStream << info.mLocalRot.x << info.mLocalRot.y << info.mLocalRot.z;
    *m_packetStream << info.mLocalRotAccel.x << info.mLocalRotAccel.y << info.mLocalRotAccel.z;

    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
    m_tel_space_timer = 0.0f;
  }

  // send telemetry user_input at about 2 hz
  if (m_tel_user_input_timer > 0.2) {
    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/telemetry/user_imput" );

    *m_packetStream << info.mUnfilteredThrottle << info.mUnfilteredBrake << info.mUnfilteredSteering;
    *m_packetStream << info.mUnfilteredClutch << info.mSteeringArmForce;

    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
    m_tel_user_input_timer = 0.0f;
  }

  // send wheel info at about 3 hz
  if (m_tel_wheel_timer > 0.1) {
    for( long i = 0; i < 4; ++i ) {
      const TelemWheelV2 &wheel = info.mWheel[i];
      m_packetStream->Clear();
      *m_packetStream << osc::BeginMessage( "/telemetry/wheel" );
      *m_packetStream << ((i==0)?"FL":(i==1)?"FR":(i==2)?"RL":"RR");
      *m_packetStream << wheel.mRotation << wheel.mSuspensionDeflection << wheel.mRideHeight;
      *m_packetStream << wheel.mTireLoad << wheel.mLateralForce << wheel.mGripFract;
      *m_packetStream << wheel.mBrakeTemp << wheel.mPressure;
      *m_packetStream << (float)wheel.mTemperature[0] << (float)wheel.mTemperature[1] << (float)wheel.mTemperature[2];
      *m_packetStream << (float)wheel.mWear << wheel.mTerrainName << (int)wheel.mSurfaceType;
      *m_packetStream << (int)wheel.mFlat << (int)wheel.mDetached;
      *m_packetStream << osc::EndMessage;
      m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
    }
    m_tel_wheel_timer = 0.0f;
  }
}


void ExampleInternalsPlugin::UpdateGraphics( const GraphicsInfoV2 &info )
{
}


bool ExampleInternalsPlugin::CheckHWControl( const char * const controlName, float &fRetVal )
{
	return false;
}


bool ExampleInternalsPlugin::ForceFeedback( float &forceValue )
{
	return false;
}


void ExampleInternalsPlugin::UpdateScoring( const ScoringInfoV2 &info )
{
  if (!m_send_scoring) {
    return;
  }
  m_scoring_global_timer += 0.5f;
  m_scoring_driver_counter ++;
  m_scoring_space_counter ++;

  if (m_scoring_global_timer > 0.5f) {
    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/scoring/global" );

  	*m_packetStream << info.mTrackName << info.mSession << info.mCurrentET << info.mEndET << info.mMaxLaps << info.mLapDist << info.mNumVehicles;
  	*m_packetStream << (int)info.mGamePhase << (int)info.mYellowFlagState << (int)info.mSectorFlag[0] << (int)info.mSectorFlag[1] << (int)info.mSectorFlag[2];
  	*m_packetStream << (int)info.mStartLight << (int)info.mNumRedLights << (int)info.mInRealtime << info.mPlayerName;
  	*m_packetStream << info.mDarkCloud << info.mRaining << info.mAmbientTemp << info.mTrackTemp << info.mWind.x << info.mWind.y << info.mWind.z << info.mOnPathWetness << info.mOffPathWetness;
    
    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
    m_scoring_global_timer = 0.0f;
  }

  // send one driver record every call
  if (info.mNumVehicles > 0) {
    long driver_index = m_scoring_driver_counter % info.mNumVehicles;
    const VehicleScoringInfoV2 &vinfo = info.mVehicle[driver_index];

    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/scoring/driver" );
    *m_packetStream << vinfo.mDriverName << vinfo.mVehicleName << (int)vinfo.mTotalLaps << (int)vinfo.mSector << (int)vinfo.mFinishStatus;
    *m_packetStream << vinfo.mLapDist << vinfo.mPathLateral << vinfo.mTrackEdge;
    *m_packetStream << vinfo.mBestSector1 << vinfo.mBestSector2 << vinfo.mBestLapTime;
    *m_packetStream << vinfo.mLastSector1 << vinfo.mLastSector2 << vinfo.mLastLapTime;
    *m_packetStream << vinfo.mCurSector1 << vinfo.mCurSector2;
    *m_packetStream << (int)vinfo.mNumPitstops << (int)vinfo.mNumPenalties << (int)vinfo.mIsPlayer << (int)vinfo.mControl << (int)vinfo.mInPits;
    *m_packetStream << (int)vinfo.mPlace << vinfo.mVehicleClass ;
    *m_packetStream << vinfo.mTimeBehindNext << vinfo.mLapsBehindNext << vinfo.mTimeBehindLeader << vinfo.mLapsBehindLeader << vinfo.mLapStartET;

    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );

    m_packetStream->Clear();
    *m_packetStream << osc::BeginMessage( "/scoring/space" );
    *m_packetStream << vinfo.mDriverName;
    *m_packetStream << vinfo.mPos.x << vinfo.mPos.y << vinfo.mPos.z;
    *m_packetStream << vinfo.mLocalVel.x << vinfo.mLocalVel.y << vinfo.mLocalVel.z;
    *m_packetStream << vinfo.mLocalAccel.x << vinfo.mLocalAccel.y << vinfo.mLocalAccel.z;
    *m_packetStream << vinfo.mOriX.x << vinfo.mOriX.y << vinfo.mOriX.z;
    *m_packetStream << vinfo.mOriY.x << vinfo.mOriY.y << vinfo.mOriY.z;
    *m_packetStream << vinfo.mOriZ.x << vinfo.mOriZ.y << vinfo.mOriZ.z;
    *m_packetStream << vinfo.mLocalRot.x << vinfo.mLocalRot.y << vinfo.mLocalRot.z;
    *m_packetStream << vinfo.mLocalRotAccel.x << vinfo.mLocalRotAccel.y << vinfo.mLocalRotAccel.z;

    *m_packetStream << osc::EndMessage;
    m_socket->Send( m_packetStream->Data(), m_packetStream->Size() );
  }
}


bool ExampleInternalsPlugin::RequestCommentary( CommentaryRequestInfo &info )
{
  return( false );
}

