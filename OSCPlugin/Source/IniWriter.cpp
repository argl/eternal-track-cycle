#include <windows.h>
#include <vector>
#include "IniWriter.h"

CIniWriter::CIniWriter( const char* szFileName )
{
	strcpy_s( m_szFileName, MAX_PATH + 1, szFileName ) ;
}

void CIniWriter::WriteInteger( const char* szSection, const char* szKey, int iValue )
{
	char szValue[MAX_PATH + 1];
	sprintf_s(szValue, MAX_PATH + 1, "%d", iValue);
	WritePrivateProfileString(szSection,  szKey, szValue, m_szFileName); 
}

void CIniWriter::WriteFloat( const char* szSection, const char* szKey, float fltValue )
{
	char szValue[MAX_PATH + 1];
	sprintf_s(szValue, MAX_PATH + 1, "%f", fltValue);
	WritePrivateProfileString(szSection,  szKey, szValue, m_szFileName); 
}

void CIniWriter::WriteBoolean( const char* szSection, const char* szKey, bool bolValue )
{
	char szValue[MAX_PATH + 1];
	sprintf_s(szValue, MAX_PATH + 1, "%s", bolValue ? "True" : "False");
	WritePrivateProfileString(szSection,  szKey, szValue, m_szFileName); 
}

void CIniWriter::WriteString( const char* szSection, const char* szKey, const char* szValue )
{
	WritePrivateProfileString(szSection,  szKey, szValue, m_szFileName);
}