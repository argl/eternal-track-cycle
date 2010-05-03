#include <windows.h>
#include <vector>
#include "IniReader.h"

CIniReader::CIniReader( const char* szFileName )
{
	strcpy_s( m_szFileName, MAX_PATH + 1, szFileName ) ;
}

int CIniReader::ReadInteger( const char* szSection, const char* szKey, int iDefaultValue )
{
	int iResult = GetPrivateProfileInt(szSection,  szKey, iDefaultValue, m_szFileName); 
	return iResult;
}

float CIniReader::ReadFloat( const char* szSection, const char* szKey, float fltDefaultValue )
{
	char szResult[ MAX_PATH + 1 ];
	char szDefault[ MAX_PATH + 1 ];
	float fltResult;
	sprintf_s( szDefault, MAX_PATH + 1, "%f", fltDefaultValue );
	GetPrivateProfileString(szSection,  szKey, szDefault, szResult, MAX_PATH, m_szFileName); 
	fltResult = (float)atof(szResult);
	return fltResult;
}

bool CIniReader::ReadBoolean( const char* szSection, const char* szKey, bool bolDefaultValue )
{
	char szResult[ MAX_PATH + 1 ];
	char szDefault[ MAX_PATH + 1 ];
	bool bolResult;
	sprintf_s( szDefault, MAX_PATH + 1, "%s", bolDefaultValue? "True" : "False" ) ;
	GetPrivateProfileString(szSection, szKey, szDefault, szResult, MAX_PATH, m_szFileName); 
	bolResult = (_stricmp(szResult, "True") == 0) ? true : false;
	return bolResult;
}

char *CIniReader::ReadString( const char* szSection, const char* szKey, const char* szDefaultValue )
{
	char *szResult = new char[ MAX_PATH + 1 ];
	memset(szResult, 0x00, MAX_PATH + 1);
	GetPrivateProfileString(szSection, szKey, szDefaultValue, szResult, MAX_PATH, m_szFileName); 
	return szResult;
}