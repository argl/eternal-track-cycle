#ifndef INIWRITER_H
#define INIWRITER_H

class CIniWriter
{
public:
	CIniWriter( const char* szFileName ); 
	void	WriteInteger( const char* szSection, const char *szKey, int iValue );
	void	WriteFloat( const char* szSection, const char *szKey, float fltValue );
	void	WriteBoolean( const char* szSection, const char *szKey, bool bolValue );
	void	WriteString( const char* szSection, const char *szKey, const char *szValue );
private:
	char	m_szFileName[MAX_PATH + 1];
};

#endif //INIWRITER_H